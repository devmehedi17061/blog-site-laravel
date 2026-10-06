<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory;

    public const SUPER_ADMIN = 'super-admin';

    /** Learners: they sign in on the public site and never see the panel. */
    public const STUDENT = 'student';

    /**
     * "Own content" is the one scope with teeth: the post and media policies
     * limit editing and deleting to the user's own records. The others
     * describe the role to whoever is assigning it.
     */
    public const SCOPES = [
        'Global' => 'Everything the permissions allow, site-wide.',
        'Content' => 'Works across all content, whoever created it.',
        'Own content' => 'May only edit and delete posts and media they created.',
        'Read only' => 'Meant for roles that look but do not change.',
    ];

    protected $fillable = ['name', 'slug', 'scope', 'description', 'is_locked'];

    protected function casts(): array
    {
        return ['is_locked' => 'boolean'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }
}
