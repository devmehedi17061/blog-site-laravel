<?php

namespace App\Actions\Menu;

use App\Models\MenuItem;
use App\Support\MenuIcons;
use Illuminate\Support\Facades\DB;

/**
 * Replaces one location's whole menu in a single transaction.
 *
 * The builder sends a **flat list**, each row carrying its own depth — the
 * shape a drag-and-drop list actually has. Nesting is rebuilt here by walking
 * the list with a stack of the last item seen at each depth, which is what
 * turns "this row is indented once" into "its parent is the row above it".
 *
 * Depth is clamped rather than trusted: a row can only ever be one level
 * deeper than the row above it, and never deeper than MenuItem::MAX_DEPTH.
 * That makes an impossible tree impossible to save, however the list arrived.
 */
class SaveMenu
{
    /** @param array<int, array<string, mixed>> $items */
    public function handle(string $location, array $items): void
    {
        DB::transaction(function () use ($location, $items) {
            $kept = [];

            // The id of the last item seen at each depth, so a row's parent is
            // whatever sits one level above it.
            $lastAtDepth = [];
            $previousDepth = -1;

            foreach (array_values($items) as $position => $input) {
                $depth = $this->clampDepth($input['depth'] ?? 0, $previousDepth);
                $parentId = $depth === 0 ? null : ($lastAtDepth[$depth - 1] ?? null);

                // A row that asked to be nested under nothing sits at the top.
                if ($depth > 0 && $parentId === null) {
                    $depth = 0;
                }

                $saved = $this->upsert($location, $input, $depth, $parentId, $position);

                $kept[] = $saved->id;
                $lastAtDepth[$depth] = $saved->id;
                $previousDepth = $depth;

                // Anything deeper than this row can no longer be its sibling's
                // parent, so those slots are dropped.
                foreach (array_keys($lastAtDepth) as $known) {
                    if ($known > $depth) {
                        unset($lastAtDepth[$known]);
                    }
                }
            }

            // Anything the client no longer sends was deleted in the builder.
            // Children go with their parent through the foreign key cascade,
            // but they are named here too so the list is exact.
            MenuItem::where('location', $location)
                ->whereNotIn('id', $kept ?: [0])
                ->delete();
        });
    }

    private function clampDepth(mixed $wanted, int $previousDepth): int
    {
        $depth = max(0, (int) $wanted);

        return min($depth, MenuItem::MAX_DEPTH, $previousDepth + 1);
    }

    private function upsert(string $location, array $input, int $depth, ?int $parentId, int $position): MenuItem
    {
        $icon = $input['icon'] ?? null;

        $attributes = [
            'location' => $location,
            'parent_id' => $parentId,
            'label' => trim((string) ($input['label'] ?? '')) ?: 'Untitled',
            // An icon the map no longer knows is dropped rather than stored,
            // so nothing can be saved that the renderer would refuse to draw.
            'icon' => MenuIcons::has($icon) ? $icon : null,
            'url' => trim((string) ($input['url'] ?? '')) ?: '/',
            'target' => in_array($input['target'] ?? null, MenuItem::TARGETS, true) ? $input['target'] : '_self',
            'visibility' => in_array($input['visibility'] ?? null, MenuItem::VISIBILITIES, true)
                ? $input['visibility']
                : 'Public',
            'depth' => $depth,
            'position' => $position,
        ];

        $existing = isset($input['id'])
            ? MenuItem::where('location', $location)->whereKey($input['id'])->first()
            : null;

        if ($existing) {
            $existing->update($attributes);

            return $existing;
        }

        return MenuItem::create($attributes);
    }
}
