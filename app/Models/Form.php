<?php

namespace App\Models;

use App\Models\Concerns\Filterable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Form extends Model
{
    use Filterable, HasFactory;

    public const STATUSES = ['Active', 'Inactive'];

    protected $fillable = [
        'name', 'slug', 'description', 'status', 'submit_label',
        'success_message', 'notify_email', 'store_submissions',
    ];

    protected array $searchable = ['name', 'slug', 'description'];

    protected array $sortable = ['name', 'status', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return ['store_submissions' => 'boolean'];
    }

    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class)->orderBy('position');
    }

    public function submissions(): HasMany
    {
        // The id breaks ties, so two submissions in the same second keep a
        // stable order instead of swapping between requests.
        return $this->hasMany(FormSubmission::class)->latest()->latest('id');
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        return in_array($status, self::STATUSES, true)
            ? $query->where('status', $status)
            : $query;
    }

    /** An inactive form still renders, but it will not take a submission. */
    public function isAccepting(): bool
    {
        return $this->status === 'Active';
    }

    public function thankYou(): string
    {
        return trim((string) $this->success_message) ?: 'Thanks — we have got your message.';
    }
}
