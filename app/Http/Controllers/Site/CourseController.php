<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LessonCompletion;
use App\Support\Lms;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The public course catalogue and each course's sales page. */
class CourseController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        // A promo link for the whole catalogue: applies at any checkout it is valid for.
        if ($request->filled('coupon')) {
            $coupon = Coupon::findByCode($request->query('coupon'));
            if ($coupon && $coupon->is_active && ! ($coupon->expires_at?->isPast())) {
                $request->session()->put('coupons.any', $coupon->code);
                $request->session()->flash('status', __('Coupon :code (:discount) is ready — it is taken off at the checkout.', ['code' => $coupon->code, 'discount' => $coupon->label()]));
            } else {
                $request->session()->flash('status', __('That coupon is not valid any more.'));
            }

            return redirect()->route('courses.index', $request->except('coupon'));
        }

        $sort = $request->query('sort', 'newest');
        $category = $request->query('category')
            ? Category::where('slug', $request->query('category'))->first()
            : null;

        $courses = Course::query()
            ->published()
            ->with(['instructor:id,name', 'category:id,name,slug', 'thumbnail'])
            ->withCount(['lessons', 'enrollments' => fn ($q) => $q->where('status', 'Active')])
            ->withAvg(['reviews' => fn ($q) => $q->where('is_approved', true)], 'rating')
            ->search($request->query('q'))
            ->level($request->query('level'))
            ->when($category, fn ($q) => $q->whereIn('category_id', $category->descendantIds()))
            ->when($request->query('price') === 'free', fn ($q) => $q->where(fn ($q) => $q->where('price', '<=', 0)->orWhere('sale_price', '<=', 0)))
            ->when($request->query('price') === 'paid', fn ($q) => $q->where('price', '>', 0)->where(fn ($q) => $q->whereNull('sale_price')->orWhere('sale_price', '>', 0)))
            ->when($sort === 'popular', fn ($q) => $q->orderByDesc('enrollments_count'))
            ->when($sort === 'price-low', fn ($q) => $q->orderBy('price'))
            ->when($sort === 'price-high', fn ($q) => $q->orderByDesc('price'))
            ->orderByDesc('is_featured')
            ->latest('published_at')
            ->paginate(9)
            ->withQueryString();

        // Only categories that actually hold a live course are offered.
        $used = Course::published()->whereNotNull('category_id')->distinct()->pluck('category_id');

        return view('frontend.courses.index', [
            'courses' => $courses,
            'categories' => Category::whereIn('id', $used)->orderBy('name')->get(['id', 'name', 'slug']),
            'levels' => Course::LEVELS,
            'filters' => [
                'q' => $request->query('q'),
                'category' => $category?->slug,
                'level' => $request->query('level'),
                'price' => $request->query('price'),
                'sort' => $sort,
            ],
        ]);
    }

    public function show(Request $request, string $slug): View|RedirectResponse
    {
        $course = $this->findForViewer($request, $slug);

        // A promo link (/courses/x?coupon=EID50): kept for the checkout,
        // for a visitor who signs in on the way too.
        if ($request->filled('coupon')) {
            EnrollController::applyFromLink($request, $course);

            return redirect()->route('courses.show', $course->slug);
        }
        $coupon = EnrollController::appliedCoupon($request, $course);
        $course->load(['instructor:id,name', 'category:id,name,slug', 'thumbnail', 'sections.lessons']);
        $course->loadCount(['lessons', 'enrollments' => fn ($q) => $q->where('status', 'Active')]);

        $user = $request->user();
        $enrollment = $user ? Enrollment::where('user_id', $user->id)->where('course_id', $course->id)->first() : null;

        $reviews = $course->reviews()->where('is_approved', true)->with('student:id,name')->latest()->limit(10)->get();
        $related = Course::published()
            ->where('category_id', $course->category_id)->whereKeyNot($course->id)
            ->with(['instructor:id,name', 'category:id,name,slug', 'thumbnail'])
            ->withCount('lessons')
            ->latest('published_at')->limit(3)->get();

        return view('frontend.courses.show', [
            'course' => $course,
            'enrollment' => $enrollment,
            'reviews' => $reviews,
            'rating' => $course->averageRating(),
            'myReview' => $user ? $course->reviews()->where('user_id', $user->id)->first() : null,
            // Lessons in order: what this student cannot open yet.
            'locked' => $enrollment?->isActive() && ! $user->can('update', $course)
                ? $course->lockedLessonIds(LessonCompletion::where('user_id', $user->id)->where('course_id', $course->id)->pluck('lesson_id')->all())
                : [],
            'related' => $related,
            'previewing' => ! $course->isPublished(),
            'coupon' => $coupon,
            'price' => Lms::priceFor($course, $coupon),
            'wishlisted' => $user ? $user->wishlist()->whereKey($course->id)->exists() : false,
            'announcements' => $enrollment?->isActive() ? $course->announcements()->with('author:id,name')->limit(5)->get() : collect(),
        ]);
    }

    /**
     * Live courses for everyone; a draft only for staff who may edit
     * courses, so an instructor can look at a course before publishing it.
     */
    public static function findForViewer(Request $request, string $slug): Course
    {
        $course = Course::where('slug', $slug)->firstOrFail();

        abort_unless($course->isPublished() || $request->user()?->can('update', $course), 404);

        return $course;
    }
}
