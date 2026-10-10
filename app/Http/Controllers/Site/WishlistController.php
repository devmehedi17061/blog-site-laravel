<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Courses a student saved for later, from the ♥ on a course page. */
class WishlistController extends Controller
{
    public function index(Request $request): View
    {
        $enrolled = $request->user()->enrollments()->whereIn('status', ['Active', 'Pending'])->pluck('course_id');

        return view('frontend.student.wishlist', [
            'courses' => $request->user()->wishlist()
                ->published()
                ->with(['instructor:id,name', 'category:id,name,slug', 'thumbnail'])
                ->withCount('lessons')
                ->withAvg(['reviews' => fn ($q) => $q->where('is_approved', true)], 'rating')
                ->orderByPivot('created_at', 'desc')
                ->get(),
            'enrolled' => $enrolled->all(),
        ]);
    }

    public function toggle(Request $request, string $slug): RedirectResponse
    {
        $course = Course::published()->where('slug', $slug)->firstOrFail();
        $result = $request->user()->wishlist()->toggle($course->id);

        return back()->with('status', $result['attached']
            ? __('Saved to your wishlist.')
            : __('Removed from your wishlist.'));
    }
}
