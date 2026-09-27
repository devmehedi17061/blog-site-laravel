<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Deleting sends the record to the Trash; forceDelete() removes it for good.
 * Also notes who moved it there, which the Trash screen shows.
 */
trait Trashable
{
    use SoftDeletes;

    public static function bootTrashable(): void
    {
        // A plain query, not save(): saving would fire "updated" and bump
        // updated_at on a record that was only moved.
        static::softDeleted(function (self $model) {
            $model->deleted_by = auth()->id();
            $model->newQueryWithoutScopes()->whereKey($model->getKey())->update(['deleted_by' => $model->deleted_by]);
        });

        static::restoring(function (self $model) {
            $model->deleted_by = null;
        });
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
