<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class FormSubmission extends Model
{
    use HasFactory;

    protected $fillable = ['form_id', 'payload', 'ip_address', 'read_at'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function scopeForForm(Builder $query, int|string|null $formId): Builder
    {
        return blank($formId) || $formId === 'all'
            ? $query
            : $query->where('form_id', (int) $formId);
    }

    public function scopeState(Builder $query, ?string $state): Builder
    {
        return match ($state) {
            'Unread' => $query->whereNull('read_at'),
            'Read' => $query->whereNotNull('read_at'),
            default => $query,
        };
    }

    /**
     * Free-text search over the answers.
     *
     * The payload is a JSON document whose keys differ per form, so there is
     * no column to search. Casting the whole document to text and matching in
     * it is coarse, but it is what "find the message from Rahman" needs, and
     * an inbox is small enough that a full scan is fine.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($term) {
            $inner->whereRaw('CAST(payload AS CHAR) LIKE ?', ['%'.$term.'%'])
                ->orWhere('ip_address', 'like', '%'.$term.'%');
        });
    }

    public function markRead(): void
    {
        if ($this->isUnread()) {
            $this->forceFill(['read_at' => now()])->save();
        }
    }

    public function markUnread(): void
    {
        $this->forceFill(['read_at' => null])->save();
    }

    /**
     * One line for the inbox list. Reads the first answer with any text in
     * it, because which field that is differs from form to form.
     */
    public function summary(): string
    {
        foreach ($this->payload ?? [] as $value) {
            $text = trim(is_array($value) ? implode(', ', $value) : (string) $value);

            if ($text !== '') {
                return Str::limit($text, 80);
            }
        }

        return 'Empty submission';
    }

    /** The next line of the row, the way an email client shows a preview. */
    public function preview(): string
    {
        $parts = [];
        $first = true;

        foreach ($this->payload ?? [] as $value) {
            $text = trim(is_array($value) ? implode(', ', $value) : (string) $value);

            if ($text === '') {
                continue;
            }

            // The first answer is already the summary above it.
            if ($first) {
                $first = false;

                continue;
            }

            $parts[] = $text;
        }

        return Str::limit(implode(' · ', $parts), 140);
    }
}
