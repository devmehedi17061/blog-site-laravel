<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Page;
use App\Models\Post;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The site root: the page chosen as the homepage in the panel. With none
 * chosen — or if it somehow stopped being live — the built-in course
 * homepage shows instead (featured courses, topics, latest posts), so "/"
 * is never an error and a fresh install already looks like a school.
 */
class HomeController extends Controller
{
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
            : response($this->courseHome());
    }

    private function courseHome()
    {
        $courses = Course::published()
            ->with(['instructor:id,name', 'category:id,name,slug', 'thumbnail'])
            ->withCount(['lessons', 'enrollments' => fn ($q) => $q->where('status', 'Active')])
            ->withAvg(['reviews' => fn ($q) => $q->where('is_approved', true)], 'rating')
            ->orderByDesc('is_featured')
            ->latest('published_at')
            ->limit(6)
            ->get();

        $topics = Category::query()
            ->whereHas('courses', fn ($q) => $q->published())
            ->withCount(['courses' => fn ($q) => $q->published()])
            ->orderByDesc('courses_count')
            ->limit(8)
            ->get();

        return view('frontend.courses.home', [
            'courses' => $courses,
            'topics' => $topics,
            'posts' => Post::published()
                ->with(['author:id,name', 'category:id,name,slug', 'featuredImage'])
                ->latest('published_at')->limit(3)->get(),
            'stats' => [
                'courses' => Course::published()->count(),
                'students' => User::whereHas('role', fn ($q) => $q->where('slug', Role::STUDENT))->count(),
                'enrollments' => Enrollment::where('status', 'Active')->count(),
                'instructors' => Course::published()->distinct()->count('user_id'),
            ],
        ]);
    }
}
