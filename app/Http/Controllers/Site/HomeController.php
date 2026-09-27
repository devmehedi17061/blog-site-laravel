<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The site root: the page chosen as the homepage in the panel. With none
 * chosen — or if it somehow stopped being live — the latest blog posts show
 * instead, so "/" is never an error.
 */
class HomeController extends Controller
{
    public function __construct(private readonly BlogController $blog) {}

    public function __invoke(Request $request): Response
    {
        $page = Page::homeId()
            ? Page::query()
                ->published()
                ->with(['sections.template', 'featuredImage', 'author:id,name'])
                ->find(Page::homeId())
            : null;

        return $page
            ? PageController::render($page)
            : response($this->blog->index($request));
    }
}
