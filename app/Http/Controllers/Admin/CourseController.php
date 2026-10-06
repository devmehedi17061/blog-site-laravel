<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveCourseRequest;
use App\Http\Resources\CourseResource;
use App\Models\ActivityLog;
use App\Models\Course;
use App\Support\Slug;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CourseController extends Controller
{
    private const RELATIONS = ['instructor:id,name', 'category:id,name', 'thumbnail'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Course::class);

        $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'between:5,100'],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);

        $courses = Course::query()
            ->with(self::RELATIONS)
            ->withCount(['lessons', 'enrollments' => fn ($q) => $q->where('status', 'Active')])
            ->search($request->input('search'))
            ->status($request->input('status'))
            ->category($request->input('category_id'))
            ->sortBy($request->input('sort'), $request->input('direction'))
            ->paginate((int) ($request->input('per_page') ?: 10))
            ->withQueryString();

        return CourseResource::collection($courses);
    }

    public function store(SaveCourseRequest $request): JsonResponse
    {
        $this->authorize('create', Course::class);

        $data = $this->normalise($request->validated());

        // An instructor's new course is always their own.
        if (empty($data['user_id']) || $request->user()->role?->scope === 'Own content') {
            $data['user_id'] = $request->user()->id;
        }

        $data['slug'] = Slug::unique(Course::class, trim((string) ($data['slug'] ?? '')) ?: $data['title']);

        $course = Course::create($data);
        ActivityLog::record('Created course', $course->title, $course);

        return CourseResource::make($course->load(self::RELATIONS))->response()->setStatusCode(201);
    }

    public function show(Course $course): CourseResource
    {
        $this->authorize('view', $course);

        return CourseResource::make($course->load(self::RELATIONS));
    }

    public function update(SaveCourseRequest $request, Course $course): CourseResource
    {
        $this->authorize('update', $course);

        $data = $this->normalise($request->validated(), $course);

        // An instructor cannot hand their course to someone else.
        if ($request->user()->role?->scope === 'Own content') {
            unset($data['user_id']);
        }

        if (array_key_exists('slug', $data)) {
            $data['slug'] = Slug::unique(Course::class, trim((string) $data['slug']) ?: ($data['title'] ?? $course->title), $course->id);
        }

        $course->update($data);
        ActivityLog::record('Updated course', $course->title, $course);

        return CourseResource::make($course->load(self::RELATIONS));
    }

    public function destroy(Course $course): JsonResponse
    {
        $this->authorize('delete', $course);

        // To the Trash: students keep their places and progress if it comes back.
        $course->delete();
        ActivityLog::record('Moved course to trash', $course->title, $course);

        return response()->json(['message' => 'Course moved to the trash.']);
    }

    /** Published without a date is stamped now; back to Draft loses the date. */
    private function normalise(array $data, ?Course $course = null): array
    {
        $status = $data['status'] ?? $course?->status ?? 'Draft';

        if ($status === 'Draft') {
            $data['published_at'] = null;
        } elseif (empty($data['published_at'])) {
            $data['published_at'] = $course?->published_at ?? now();
        }

        if (array_key_exists('price', $data)) {
            $data['price'] = $data['price'] ?? 0;
        }

        return $data;
    }
}
