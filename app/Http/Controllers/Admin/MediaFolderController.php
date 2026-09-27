<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMediaFolderRequest;
use App\Http\Resources\MediaFolderResource;
use App\Models\Media;
use App\Models\MediaFolder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class MediaFolderController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Media::class);

        // The sidebar needs library-wide totals, not the counts for whichever
        // folder happens to be open.
        return MediaFolderResource::collection(
            MediaFolder::withCount('media')->orderBy('name')->get()
        )->additional(['meta' => [
            'total' => Media::count(),
            'unfiled' => Media::whereNull('folder_id')->count(),
        ]]);
    }

    public function store(StoreMediaFolderRequest $request): JsonResponse
    {
        $this->authorize('create', Media::class);

        $folder = MediaFolder::create([
            'name' => $request->input('name'),
            'slug' => Str::slug($request->input('name')).'-'.Str::lower(Str::random(4)),
        ]);

        return MediaFolderResource::make($folder)->response()->setStatusCode(201);
    }

    public function update(StoreMediaFolderRequest $request, MediaFolder $folder): MediaFolderResource
    {
        // Renaming touches a shared grouping, so it needs edit rights rather
        // than the upload right that lets an author add their own files.
        abort_unless($request->user()->hasPermission('media.update'), 403);

        $folder->update(['name' => $request->input('name')]);

        return MediaFolderResource::make($folder->loadCount('media'));
    }

    /** Files are kept and fall back to Unfiled — deleting a folder is not a purge. */
    public function destroy(Request $request, MediaFolder $folder): JsonResponse
    {
        // Deleting a folder re-files everybody's uploads, not just your own.
        abort_unless($request->user()->hasPermission('media.delete'), 403);

        $folder->media()->update(['folder_id' => null]);
        $folder->delete();

        return response()->json(['message' => 'Folder deleted. Its files moved to Unfiled.']);
    }
}
