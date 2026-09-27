<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A header or a footer from Builders → Header & footer: HTML, CSS and fields
 * like a library section, plus the values, since the whole site shares it.
 * One of each kind is in use (is_active).
 */
class SitePart extends Model
{
    public const KINDS = ['header', 'footer'];

    /** The drop-down arrow beside a menu item with a sub-menu (site-parts/caret). */
    public const CARETS = ['triangle', 'chevron', 'plus', 'arrow', 'none'];

    protected $fillable = ['kind', 'name', 'slug', 'description', 'html', 'css', 'fields', 'values', 'settings', 'is_active', 'user_id'];

    /** @var array<string, ?SitePart> the part in use, per kind, for this request */
    private static array $activeCache = [];

    protected function casts(): array
    {
        return [
            'fields' => 'array',
            'values' => 'array',
            'settings' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::$activeCache = []);
        static::deleted(fn () => static::$activeCache = []);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** The header or footer the site shows. Asked for on every page, so read once. */
    public static function active(string $kind): ?self
    {
        if (! array_key_exists($kind, static::$activeCache)) {
            static::$activeCache[$kind] = static::where('kind', $kind)->where('is_active', true)->first();
        }

        return static::$activeCache[$kind];
    }

    /** Makes this the one in use; the other of its kind steps down. */
    public function activate(): void
    {
        static::where('kind', $this->kind)->whereKeyNot($this->id)->update(['is_active' => false]);
        $this->update(['is_active' => true]);
    }

    public function caret(): string
    {
        $caret = $this->settings['caret'] ?? 'triangle';

        return in_array($caret, self::CARETS, true) ? $caret : 'triangle';
    }

    public function sticky(): bool
    {
        return $this->kind === 'header' && (bool) ($this->settings['sticky'] ?? true);
    }
}
