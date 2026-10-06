<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseReview;
use App\Support\Lms;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** One rating per student per course, from students who are on it. */
class ReviewController extends Controller
{
    public function store(Request $request, string $slug): RedirectResponse
    {
        abort_unless(Lms::setting('reviews'), 404);

        $course = Course::published()->where('slug', $slug)->firstOrFail();
        abort_unless($request->user()->activeEnrollmentFor($course), 403, 'Only students on this course can review it.');

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        CourseReview::updateOrCreate(
            ['user_id' => $request->user()->id, 'course_id' => $course->id],
            $data + ['is_approved' => true]
        );

        return redirect()->to(route('courses.show', $course->slug).'#reviews')
            ->with('status', 'Thanks for your review!');
    }
}
