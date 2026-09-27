<?php

namespace App\Models;

use App\Models\Concerns\Filterable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use Filterable, HasApiTokens, HasFactory, Notifiable;

    public const STATUSES = ['Active', 'Suspended'];

    protected array $searchable = ['name', 'email'];

    protected array $sortable = ['name', 'email', 'status', 'last_seen_at', 'created_at'];

    protected $fillable = [
        'role_id',
        'name',
        'email',
        'password',
        'status',
        'avatar',
        'last_seen_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected $appends = ['initials'];

    private ?Collection $permissionSlugCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'password' => 'hashed',
            // Encrypted with the app key: a copied database alone cannot
            // produce codes or use a recovery code.
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'password_changed_at' => 'datetime',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        return in_array($status, self::STATUSES, true)
            ? $query->where('status', $status)
            : $query;
    }

    public function scopeRole(Builder $query, int|string|null $roleId): Builder
    {
        return is_numeric($roleId) ? $query->where('role_id', (int) $roleId) : $query;
    }

    /** Password expiry counts from the last real change of password. */
    protected static function booted(): void
    {
        static::saving(function (User $user) {
            if ($user->isDirty('password')) {
                $user->password_changed_at = now();
            }
        });
    }

    /** 2FA is on only once a first code was typed back successfully. */
    public function hasTwoFactor(): bool
    {
        return $this->two_factor_method !== null && $this->two_factor_confirmed_at !== null;
    }

    public function isActive(): bool
    {
        return $this->status === 'Active';
    }

    public function isSuperAdmin(): bool
    {
        return $this->role?->slug === Role::SUPER_ADMIN;
    }

    /** The panel must always keep one active super admin who can get back in. */
    public static function otherActiveSuperAdminExists(User $except): bool
    {
        return static::query()
            ->whereKeyNot($except->getKey())
            ->where('status', 'Active')
            ->whereHas('role', fn (Builder $role) => $role->where('slug', Role::SUPER_ADMIN))
            ->exists();
    }

    public function hasPermission(string $slug): bool
    {
        if (! $this->role) {
            return false;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->permissionSlugs()->contains($slug);
    }

    /**
     * Nobody can hand out access they do not hold themselves — otherwise
     * anyone with roles.update could write themselves a bigger role, and
     * anyone with users.update could promote a friend into one. Only a
     * super admin can make another super admin.
     */
    public function canGrant(?Role $role): bool
    {
        if ($role === null || $this->isSuperAdmin()) {
            return true;
        }

        if ($role->slug === Role::SUPER_ADMIN) {
            return false;
        }

        $slugs = $role->relationLoaded('permissions')
            ? $role->permissions->pluck('slug')
            : $role->permissions()->pluck('slug');

        return $this->canGrantPermissions($slugs->all());
    }

    /** @param  array<int, string>  $slugs */
    public function canGrantPermissions(array $slugs): bool
    {
        return $this->isSuperAdmin() || collect($slugs)->diff($this->permissionSlugs())->isEmpty();
    }

    /**
     * Whether this user may edit or remove another account: never one with
     * more access than their own, so an editor cannot suspend the admin.
     */
    public function canManage(User $other): bool
    {
        return $this->isSuperAdmin() || $this->is($other) || $this->canGrant($other->role);
    }

    /**
     * Memoised for the request: policies are checked once per row per
     * ability on an index screen, which would otherwise be a query each.
     */
    public function permissionSlugs(): Collection
    {
        return $this->permissionSlugCache ??= $this->role
            ? $this->role->permissions()->pluck('slug')
            : collect();
    }

    public function getInitialsAttribute(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        $first = mb_substr($parts[0] ?? '', 0, 1);
        $last = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';

        return mb_strtoupper($first.$last) ?: 'NA';
    }
}
