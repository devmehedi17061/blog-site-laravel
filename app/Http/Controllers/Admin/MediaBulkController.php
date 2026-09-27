<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkMediaRequest;
use App\Models\ActivityLog;
use App\Models\Media;
use Illuminate\Http\JsonResponse;

class MediaBulkController extends Controller
{
    public function __invoke(BulkMediaRequest $request): JsonResponse
    {
        $items = Media::whereIn('id', $request->input('ids'))->get();

        if ($request->input('action') === 'move') {
            foreach ($items as $media) {
                $this->authorize('update', $media);
            }

            Media::whereIn('id', $items->pluck('id'))
                ->update(['folder_id' => $request->input('folder_id')]);

            ActivityLog::record('Moved files', $items->count().' file(s) moved');

            return response()->json(['message' => $items->count().' file(s) moved.']);
        }

        foreach ($items as $media) {
            $this->authorize('delete', $media);
        }

        foreach ($items as $media) {
            $media->delete();
        }

        ActivityLog::record('Moved files to trash', $items->count().' file(s)');

        return response()->json(['message' => $items->count().' file(s) moved to the trash.']);
    }
}
