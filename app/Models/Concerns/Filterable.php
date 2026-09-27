<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Shared list-screen behaviour: free-text search and whitelisted sorting.
 *
 * A model opts in by declaring the columns it exposes:
 *
 *   protected array $searchable = ['title', 'excerpt'];
 *   protected array $sortable   = ['title', 'created_at'];
 */
trait Filterable
{
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        $columns = $this->searchable ?? [];

        if ($term === '' || $columns === []) {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($columns, $term) {
            foreach ($columns as $column) {
                $inner->orWhere($column, 'like', '%'.$term.'%');
            }
        });
    }

    /**
     * Falls back to the newest-first default when the column is not
     * whitelisted, so a hand-edited query string cannot leak ordering
     * on arbitrary columns.
     */
    public function scopeSortBy(Builder $query, ?string $column, ?string $direction = 'desc'): Builder
    {
        $allowed = $this->sortable ?? [];
        $direction = strtolower((string) $direction) === 'asc' ? 'asc' : 'desc';

        if (! in_array($column, $allowed, true)) {
            return $query->latest($this->getCreatedAtColumn());
        }

        return $query->orderBy($column, $direction);
    }
}
