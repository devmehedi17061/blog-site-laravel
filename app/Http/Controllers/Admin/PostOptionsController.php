<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use App\Support\Seo;
use Illuminate\Http\JsonResponse;

/**
 * Feeds the post form's select boxes in one request, so the screen does not
 * fan out to three endpoints just to open a modal.
 */
class PostOptionsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $this->authorize('viewAny', Post::class);

        return response()->json([
            'statuses' => Post::STATUSES,
            // Flat but in tree order, each carrying its depth, so the
            // dropdown can indent a sub-category under its parent.
            'categories' => Category::flat()->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'depth' => $category->depth,
                'path' => $category->path(),
            ])->values(),
            'authors' => User::where('status', 'Active')->orderBy('name')->get(['id', 'name']),
            'robots' => collect(Seo::ROBOTS)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
        ]);
    }
}
