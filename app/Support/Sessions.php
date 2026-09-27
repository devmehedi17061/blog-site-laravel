<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Signed-in sessions, read from the database session store: where someone
 * is signed in, from what, and when they were last active.
 */
class Sessions
{
    public static function available(): bool
    {
        return config('session.driver') === 'database';
    }

    private static function table()
    {
        return DB::table(config('session.table', 'sessions'));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function list(?int $userId = null, ?string $currentId = null): Collection
    {
        if (! self::available()) {
            return collect();
        }

        $cutoff = now()->subMinutes((int) config('session.lifetime'))->timestamp;

        $rows = self::table()
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', $cutoff)
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->orderByDesc('last_activity')
            ->get(['id', 'user_id', 'ip_address', 'user_agent', 'last_activity']);

        $names = User::whereIn('id', $rows->pluck('user_id')->unique())->pluck('name', 'id');

        return $rows->map(fn ($row) => [
            // Never the raw id: it is the session cookie's value.
            'key' => hash('sha256', $row->id),
            'user_id' => (int) $row->user_id,
            'user' => $names[$row->user_id] ?? 'Unknown',
            'ip' => $row->ip_address,
            'device' => self::describe((string) $row->user_agent),
            'last_active' => Carbon::createFromTimestamp($row->last_activity)->diffForHumans(),
            'current' => $currentId !== null && hash_equals($row->id, $currentId),
        ]);
    }

    /** Ends one session, found by its hashed key. */
    public static function end(string $key, ?int $userId = null): int
    {
        if (! self::available()) {
            return 0;
        }

        $ids = self::table()
            ->whereNotNull('user_id')
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->pluck('id')
            ->filter(fn ($id) => hash_equals(hash('sha256', $id), $key));

        return $ids->isEmpty() ? 0 : self::table()->whereIn('id', $ids)->delete();
    }

    /** Every session of one person, except (optionally) the one in use. */
    public static function endAllFor(User $user, ?string $exceptId = null): int
    {
        // "Keep me signed in" cookies are keyed to this token: change it and
        // they stop working as well.
        $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();

        if (! self::available()) {
            return 0;
        }

        return self::table()
            ->where('user_id', $user->id)
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->delete();
    }

    /** "Chrome on Windows" from a user-agent string. */
    public static function describe(string $agent): string
    {
        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') || str_contains($agent, 'Opera') => 'Opera',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            $agent === '' => 'Unknown browser',
            default => 'Browser',
        };

        $system = match (true) {
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone') || str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Mac OS X') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'unknown system',
        };

        return "{$browser} on {$system}";
    }
}
