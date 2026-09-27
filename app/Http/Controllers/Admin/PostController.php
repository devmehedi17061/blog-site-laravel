<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Posts\SavePost;
use App\Http\Controllers\Controller;
use App\Http\Requests\PostIndexRequest;
use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Http\Resources\PostResource;
use App\Models\ActivityLog;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PostController extends Controller
{
    public function __construct(private readonly SavePost $savePost) {}

    public function index(PostIndexRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Post::class);
        Post::publishDue();

        $posts = Post::query()
            ->with(['author:id,name', 'category:id,name', 'featuredImage'])
            ->search($request->input('search'))
            ->status($request->input('status'))
            ->category($request->input('category_id'))
            ->sortBy($request->input('sort'), $request->input('direction'))
            ->paginate($request->perPage())
            ->withQueryString();

        return PostResource::collection($posts);
    }

    public function store(StorePostRequest $request): JsonResponse
    {
        $this->authorize('create', Post::class);

        $post = $this->savePost->create($request->validated(), $request->user()->id);

        return PostResource::make($post->load(['author:id,name', 'category:id,name', 'featuredImage']))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Post $post): PostResource
    {
        $this->authorize('view', $post);

        return PostResource::make($post->load(['author:id,name', 'category:id,name', 'featuredImage']));
    }

    public function update(UpdatePostRequest $request, Post $post): PostResource
    {
        $this->authorize('update', $post);

        $post = $this->savePost->update($post, $request->validated());

        return PostResource::make($post->load(['author:id,name', 'category:id,name', 'featuredImage']));
    }

    public function destroy(Post $post): JsonResponse
    {
        $this->authorize('delete', $post);

        // To the Trash: restorable from there until it is emptied.
        $post->delete();

        ActivityLog::record('Moved post to trash', $post->title, $post);

        return response()->json(['message' => 'Post moved to the trash.']);
    }
}
