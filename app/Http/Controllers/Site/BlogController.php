<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The public blog. Rendered server side with Blade so search engines and
 * social previews get real HTML rather than an empty shell.
 */
class BlogController extends Controller
{
    public function index(Request $request): View
    {
        $posts = Post::query()
            ->published()
            ->with(['author:id,name', 'category:id,name,slug', 'featuredImage'])
            ->search($request->query('q'))
            ->latest('published_at')
            ->paginate(9)
            ->withQueryString();

        return view('frontend.blog.index', [
            'posts' => $posts,
            // Flat but in tree order, so the sidebar can indent the
            // sub-categories under the ones they belong to.
            'categories' => Category::flat(),
            'query' => $request->query('q'),
        ]);
    }

    public function show(string $slug): View
    {
        $post = Post::query()
            ->published()
            ->with(['author:id,name', 'category:id,name,slug', 'featuredImage'])
            ->where('slug', $slug)
            ->firstOrFail();

        $related = Post::query()
            ->published()
            ->where('category_id', $post->category_id)
            ->whereKeyNot($post->id)
            ->with(['author:id,name', 'category:id,name,slug', 'featuredImage'])
            ->latest('published_at')
            ->limit(3)
            ->get();

        return view('frontend.blog.show', compact('post', 'related'));
    }

    /**
     * A category archive lists its own posts *and* everything filed under it,
     * because a parent whose children hold all the writing would otherwise
     * look empty — which is not what anyone clicking "Engineering" means.
     */
    public function category(string $slug): View
    {
        $category = Category::with('image')->where('slug', $slug)->firstOrFail();

        $posts = Post::query()
            ->published()
            ->with(['author:id,name', 'category:id,name,slug', 'featuredImage'])
            ->whereIn('category_id', $category->descendantIds())
            ->latest('published_at')
            ->paginate(9);

        return view('frontend.blog.category', [
            'category' => $category,
            'posts' => $posts,
            'children' => $category->children()->withCount('posts')->get(),
            'ancestors' => $category->ancestors(),
        ]);
    }
}
