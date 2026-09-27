<?php

namespace App\Models\Concerns;

use App\Models\Revision;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Keeps every saved version of a record, so an earlier one can be compared
 * with the current one and brought back.
 *
 * The model says what a version holds (revisionData), how to put one back
 * (applyRevision) and how to show its fields (revisionFields). Saving code
 * calls ensureBaseline() before it writes and recordRevision() after.
 */
trait HasRevisions
{
    /** Older versions than this many are dropped. */
    public const REVISION_LIMIT = 50;

    /**
     * Saves by the same person this close together become one version: a
     * page's details and its sections go in two requests but are one save.
     */
    public const REVISION_MERGE_SECONDS = 10;

    /** Everything a version holds, as plain JSON-safe values. */
    abstract public function revisionData(): array;

    /** Puts a version's content back. Status, address and author stay as they are. */
    abstract public function applyRevision(array $data): void;

    /** @return array<string, array{0: string, 1: string}> key => [label, kind] */
    abstract public function revisionFields(): array;

    public static function bootHasRevisions(): void
    {
        // A record sent to the Trash keeps its history; one deleted for good
        // takes it along.
        static::deleted(function (self $model) {
            if (! method_exists($model, 'isForceDeleting') || $model->isForceDeleting()) {
                $model->revisions()->delete();
            }
        });
    }

    public function revisions(): MorphMany
    {
        return $this->morphMany(Revision::class, 'revisionable')->latest('id');
    }

    /**
     * Content written before history existed has no versions. The first
     * save would lose how it looked, so it is kept first, dated when it was
     * last changed.
     */
    public function ensureBaseline(): void
    {
        if (! $this->exists || $this->revisions()->exists()) {
            return;
        }

        $data = $this->revisionData();
        $revision = $this->revisions()->make([
            'user_id' => null,
            'label' => 'Original version',
            'data' => $data,
            'hash' => Revision::hashOf($data),
        ]);
        $revision->created_at = $this->updated_at ?? now();
        $revision->save();
    }

    /** Stores the record as it is now, unless nothing changed since the last version. */
    public function recordRevision(?string $label = null): Revision
    {
        $data = $this->revisionData();
        $hash = Revision::hashOf($data);
        $latest = $this->revisions()->first();

        if ($latest && $latest->hash === $hash && $label === null) {
            return $latest;
        }

        $mergeable = $latest
            && $label === null
            && $latest->label === null
            && $latest->user_id === auth()->id()
            && $latest->created_at?->gt(now()->subSeconds(self::REVISION_MERGE_SECONDS));

        if ($mergeable) {
            $latest->update(['data' => $data, 'hash' => $hash]);

            return $latest;
        }

        $revision = $this->revisions()->create([
            'user_id' => auth()->id(),
            'label' => $label,
            'data' => $data,
            'hash' => $hash,
        ]);

        $this->pruneRevisions();

        return $revision;
    }

    private function pruneRevisions(): void
    {
        $keep = $this->revisions()->limit(self::REVISION_LIMIT)->pluck('id');

        $this->revisions()->getQuery()->whereNotIn('id', $keep)->delete();
    }
}
