<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * An old address and where it now leads.
 *
 * Kept chain-free: when B moves to C, anything that led to B is pointed
 * straight at C, so a visitor (and Google) takes one hop, never several.
 * And an address that has live content again stops redirecting.
 */
class Redirect extends Model
{
    public const STATUSES = [301, 302];

    protected $fillable = ['from_path', 'to_path', 'status', 'source', 'note', 'hits', 'last_hit_at'];

    protected function casts(): array
    {
        return ['last_hit_at' => 'datetime', 'status' => 'integer', 'hits' => 'integer'];
    }

    /** "/Blog/Old-Post/" → "/blog/old-post" style: one leading slash, no trailing one. */
    public static function normalise(string $path): string
    {
        $path = '/'.trim(parse_url($path, PHP_URL_PATH) ?? $path, '/');

        return rawurldecode($path);
    }

    /**
     * Something live moved from $old to $new: remember it. Called by the
     * models themselves when a slug changes (see HasRedirects).
     */
    public static function moved(string $old, string $new): void
    {
        $old = self::normalise($old);
        $new = self::normalise($new);

        if ($old === $new || $old === '/') {
            return;
        }

        DB::transaction(function () use ($old, $new) {
            // The new address is live now: nothing may redirect away from it.
            self::where('from_path', $new)->delete();

            // Anything that led to the old address leads straight to the new.
            self::where('to_path', $old)->update(['to_path' => $new]);

            self::updateOrCreate(['from_path' => $old], ['to_path' => $new, 'status' => 301, 'source' => 'auto']);
        });
    }

    /** Content now lives at this address again: stop redirecting it. */
    public static function released(string $path): void
    {
        self::where('from_path', self::normalise($path))->delete();
    }

    /** Where following $to ends up, to refuse loops before they are saved. */
    public static function leadsBackTo(string $to, string $from): bool
    {
        $seen = [];
        $at = self::normalise($to);

        while (! in_array($at, $seen, true) && count($seen) < 20) {
            if ($at === self::normalise($from)) {
                return true;
            }
            $seen[] = $at;
            $next = self::where('from_path', $at)->value('to_path');
            if ($next === null || preg_match('#^https?://#i', $next)) {
                return false;
            }
            $at = self::normalise($next);
        }

        return true;
    }
}
