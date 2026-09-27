<?php

namespace App\Models;

use App\Models\Concerns\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A reusable section: the HTML, the CSS and the field definitions. Pages hold
 * their own values, so editing a template updates every page that uses it
 * without touching what anyone wrote.
 */
class SectionTemplate extends Model
{
    use Filterable, HasFactory;

    /** Field types an author can put on a template. */
    public const FIELD_TYPES = ['text', 'richtext', 'media', 'url', 'select', 'form', 'group'];

    protected $fillable = ['user_id', 'name', 'slug', 'description', 'html', 'css', 'fields'];

    protected array $searchable = ['name', 'description'];

    protected array $sortable = ['name', 'created_at'];

    protected function casts(): array
    {
        return ['fields' => 'array'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class, 'template_id');
    }
}
