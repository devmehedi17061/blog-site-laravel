<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MediaIndexRequest;
use App\Http\Requests\StoreMediaRequest;
use App\Http\Requests\UpdateMediaRequest;
use App\Http\Resources\MediaResource;
use App\Models\ActivityLog;
use App\Models\Media;
use App\Support\ImageVariants;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    public function index(MediaIndexRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Media::class);

        $media = Media::query()
            ->with(['folder:id,name', 'user:id,name'])
            ->search($request->input('search'))
            ->folder($request->input('folder'))
            ->kind($request->input('kind'))
            ->sortBy($request->input('sort'), $request->input('direction'))
            ->paginate($request->perPage())
            ->withQueryString();

        return MediaResource::collection($media);
    }

    public function show(MediaIndexRequest $request, Media $media): MediaResource
    {
        $this->authorize('view', $media);

        return MediaResource::make($media->load(['folder:id,name', 'user:id,name']));
    }

    /** Accepts several files at once; each is reported back individually. */
    public function store(StoreMediaRequest $request): JsonResponse
    {
        $this->authorize('create', Media::class);

        $saved = [];

        foreach ($request->file('files') as $file) {
            $path = $file->store('media', 'public');

            $saved[] = Media::create([
                'user_id' => $request->user()->id,
                'folder_id' => $request->input('folder_id'),
                'name' => $file->getClientOriginalName(),
                'file_name' => basename($path),
                'path' => $path,
                'disk' => 'public',
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        // Smaller WebP copies for srcset, and the image's dimensions. A file
        // GD cannot read is simply left as it is.
        foreach ($saved as $media) {
            ImageVariants::generate($media);
        }

        ActivityLog::record('Uploaded files', count($saved).' file(s) added to the library');

        return MediaResource::collection(
            Media::whereIn('id', collect($saved)->pluck('id'))->with('folder:id,name')->get()
        )->response()->setStatusCode(201);
    }

    public function update(UpdateMediaRequest $request, Media $media): MediaResource
    {
        $this->authorize('update', $media);

        $media->update($request->validated());

        ActivityLog::record('Updated file', $media->name, $media);

        return MediaResource::make($media->fresh()->load('folder:id,name'));
    }

    public function destroy(Media $media): JsonResponse
    {
        $this->authorize('delete', $media);

        // To the Trash. The file stays on disk until the trash is emptied
        // (Media::booted), so posts showing it keep working if it comes back.
        $media->delete();

        ActivityLog::record('Moved file to trash', $media->name, $media);

        return response()->json(['message' => 'File moved to the trash.']);
    }
}
