<?php

namespace App\Models\Concerns;

use App\Models\Redirect;

/**
 * A model with a public address that should keep working when its slug
 * changes. The model says what its address is for a given slug; this
 * records the move whenever the old address could have been seen.
 */
trait HasRedirects
{
    /** The public path this record has, or would have, with a given slug. */
    abstract public function pathForSlug(string $slug): string;

    /** Whether the old address was public — nobody links to a draft. */
    abstract protected function wasPublic(): bool;

    /** Where the old address should now lead: normally the new one. */
    protected function redirectTarget(): string
    {
        return $this->pathForSlug((string) $this->slug);
    }

    public static function bootHasRedirects(): void
    {
        static::updated(function (self $model) {
            if (! $model->wasChanged('slug')) {
                return;
            }

            $old = (string) $model->getOriginal('slug');

            if ($old !== '' && $model->wasPublic()) {
                Redirect::moved($model->pathForSlug($old), $model->redirectTarget());
            } else {
                // Still frees the new address if an old redirect claimed it.
                Redirect::released($model->pathForSlug((string) $model->slug));
            }
        });

        static::created(function (self $model) {
            Redirect::released($model->pathForSlug((string) $model->slug));
        });
    }
}
