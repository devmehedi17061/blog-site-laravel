<?php

namespace App\Models;

use App\Models\Concerns\Filterable;
use App\Models\Concerns\Trashable;
use App\Support\ImageVariants;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    use Filterable, HasFactory, Trashable;

    protected $table = 'media';

    protected $fillable = [
        'user_id', 'folder_id', 'name', 'file_name', 'path',
        'disk', 'mime_type', 'size', 'alt_text', 'caption',
    ];

    protected array $searchable = ['name', 'file_name', 'alt_text', 'caption'];

    protected array $sortable = ['name', 'size', 'created_at'];

    protected $appends = ['url', 'human_size', 'kind'];

    protected function casts(): array
    {
        return ['variants' => 'array', 'width' => 'integer', 'height' => 'integer'];
    }

    /**
     * The file stays on disk while the record is in the Trash, so restoring
     * it brings back every post that shows it. Only a permanent delete
     * removes the file and its smaller WebP copies (ImageVariants).
     */
    protected static function booted(): void
    {
        static::forceDeleting(function (Media $media) {
            ImageVariants::removeFiles($media);
            Storage::disk($media->disk)->delete($media->path);
        });
    }

    /** "…-480.webp 480w, …-960.webp 960w" — empty when no copies were made. */
    public function srcset(): string
    {
        $disk = Storage::disk($this->disk);
        $variants = collect((array) ($this->variants ?? []));

        if ($variants->isEmpty()) {
            return '';
        }

        $candidates = $variants->map(fn ($path, $width) => [(int) $width, $disk->url($path)]);

        // No copy at full width (the original was already WebP): the
        // original itself is the largest candidate.
        if ($this->width && ! $variants->has((string) $this->width)) {
            $candidates->push([$this->width, $this->url]);
        }

        return $candidates->sortBy(0)->map(fn ($c) => $c[1].' '.$c[0].'w')->implode(', ');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class, 'folder_id');
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    /** A coarse bucket the UI uses to pick a thumbnail or an icon. */
    public function getKindAttribute(): string
    {
        $mime = (string) $this->mime_type;

        return match (true) {
            str_starts_with($mime, 'image/') => 'image',
            str_starts_with($mime, 'video/') => 'video',
            str_starts_with($mime, 'audio/') => 'audio',
            str_contains($mime, 'pdf') => 'pdf',
            str_contains($mime, 'sheet') || str_contains($mime, 'excel') || str_contains($mime, 'csv') => 'sheet',
            str_contains($mime, 'word') || str_contains($mime, 'document') => 'doc',
            str_contains($mime, 'zip') || str_contains($mime, 'compressed') => 'archive',
            default => 'file',
        };
    }

    public function getHumanSizeAttribute(): string
    {
        $bytes = (int) $this->size;

        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($bytes < 1024) {
                return round($bytes, $unit === 'B' ? 0 : 1).' '.$unit;
            }
            $bytes /= 1024;
        }

        return round($bytes, 1).' TB';
    }

    public function scopeFolder(Builder $query, int|string|null $folderId): Builder
    {
        if ($folderId === null || $folderId === '' || $folderId === 'all') {
            return $query;
        }

        // "unfiled" is the pseudo-folder for everything with no folder set.
        if ($folderId === 'unfiled') {
            return $query->whereNull('folder_id');
        }

        return $query->where('folder_id', $folderId);
    }

    public function scopeKind(Builder $query, ?string $kind): Builder
    {
        return match ($kind) {
            'image' => $query->where('mime_type', 'like', 'image/%'),
            'document' => $query->where('mime_type', 'not like', 'image/%'),
            default => $query,
        };
    }
}
