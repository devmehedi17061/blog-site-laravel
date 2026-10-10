<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveCourseRequest;
use App\Http\Resources\CourseResource;
use App\Models\ActivityLog;
use App\Models\Course;
use App\Support\LessonVideos;
use App\Support\Slug;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

    /**
     * A copy to start the next course from: details, curriculum, quiz
     * questions and uploaded videos (copied into the new course's own
     * folder). Saved as a draft; students, payments and reviews stay behind.
     */
    public function duplicate(Request $request, Course $course): JsonResponse
    {
        $this->authorize('view', $course);
        $this->authorize('create', Course::class);

        $copy = DB::transaction(function () use ($request, $course) {
            $copy = $course->replicate(['slug', 'published_at', 'deleted_at', 'deleted_by']);
            $copy->fill([
                'title' => Str::limit($course->title.' (copy)', 180, ''),
                'slug' => Slug::unique(Course::class, $course->title.' copy'),
                'status' => 'Draft',
                'published_at' => null,
                'is_featured' => false,
                'user_id' => $request->user()->role?->scope === 'Own content' ? $request->user()->id : $course->user_id,
            ])->save();

            foreach ($course->sections()->with('lessons.questions')->get() as $section) {
                $newSection = $section->replicate()->fill(['course_id' => $copy->id]);
                $newSection->save();

                foreach ($section->lessons as $lesson) {
                    $newLesson = $lesson->replicate()->fill(['course_id' => $copy->id, 'course_section_id' => $newSection->id]);

                    if ($lesson->hasUpload() && LessonVideos::disk()->exists($lesson->video_path)) {
                        $path = $copy->id.'/'.Str::random(40).'.'.pathinfo($lesson->video_path, PATHINFO_EXTENSION);
                        LessonVideos::disk()->copy($lesson->video_path, $path);
                        $newLesson->video_path = $path;
                    } else {
                        $newLesson->video_path = null;
                    }

                    $newLesson->save();

                    foreach ($lesson->questions as $question) {
                        $question->replicate()->fill(['lesson_id' => $newLesson->id])->save();
                    }
                }
            }

            return $copy;
        });

        ActivityLog::record('Duplicated course', $course->title.' → '.$copy->title, $copy);

        return response()->json(['message' => 'Copied as a draft: '.$copy->title, 'id' => $copy->id], 201);
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
