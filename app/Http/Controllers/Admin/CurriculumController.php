<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Support\LessonVideos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * A course's sections and lessons, read and saved as one tree — the same
 * approach as a page's sections: the editor holds the whole list, and a save
 * replaces it in one transaction, so a half-saved curriculum never exists.
 *
 * Existing rows keep their ids (and with them every student's progress);
 * rows missing from the list are deleted.
 */
class CurriculumController extends Controller
{
    public function show(Course $course): JsonResponse
    {
        $this->authorize('view', $course);

        return response()->json(['data' => $this->tree($course)]);
    }

    public function update(Request $request, Course $course): JsonResponse
    {
        $this->authorize('update', $course);

        $data = $request->validate([
            'sections' => ['present', 'array', 'max:100'],
            'sections.*.id' => ['nullable', 'integer'],
            'sections.*.title' => ['required', 'string', 'max:180'],
            'sections.*.lessons' => ['present', 'array', 'max:200'],
            'sections.*.lessons.*.id' => ['nullable', 'integer'],
            'sections.*.lessons.*.title' => ['required', 'string', 'max:180'],
            'sections.*.lessons.*.type' => ['required', Rule::in(Lesson::TYPES)],
            'sections.*.lessons.*.video_url' => ['nullable', 'string', 'max:500'],
            // An uploaded video: only a file this course uploaded is accepted,
            // so a hand-made request cannot point a lesson at someone else's.
            'sections.*.lessons.*.video_path' => ['nullable', 'string', 'max:255', function ($attribute, $value, $fail) use ($course) {
                if (! LessonVideos::belongsTo($course->id, $value)) {
                    $fail('That uploaded video was not found. Upload it again.');
                }
            }],
            'sections.*.lessons.*.video_name' => ['nullable', 'string', 'max:255'],
            'sections.*.lessons.*.body' => ['nullable', 'string'],
            'sections.*.lessons.*.media_id' => ['nullable', 'integer', 'exists:media,id'],
            'sections.*.lessons.*.duration_minutes' => ['nullable', 'integer', 'min:0', 'max:6000'],
            'sections.*.lessons.*.is_preview' => ['nullable', 'boolean'],
        ], [
            'sections.*.title.required' => 'Every section needs a title.',
            'sections.*.lessons.*.title.required' => 'Every lesson needs a title.',
        ]);

        // Uploaded videos in use before the save, to delete the ones dropped.
        $videosBefore = $course->lessons()->whereNotNull('video_path')->pluck('video_path')->all();

        DB::transaction(function () use ($course, $data) {
            // Only ids that already belong to this course are reused, so a
            // hand-made request cannot move another course's lessons here.
            $sectionIds = $course->sections()->pluck('id')->all();
            $lessonIds = $course->lessons()->pluck('id')->all();
            $keptSections = [];
            $keptLessons = [];

            foreach (array_values($data['sections']) as $s => $sectionData) {
                $section = in_array((int) ($sectionData['id'] ?? 0), $sectionIds, true)
                    ? CourseSection::find($sectionData['id'])
                    : new CourseSection(['course_id' => $course->id]);

                $section->fill(['title' => $sectionData['title'], 'position' => $s])->save();
                $keptSections[] = $section->id;

                foreach (array_values($sectionData['lessons']) as $l => $lessonData) {
                    $lesson = in_array((int) ($lessonData['id'] ?? 0), $lessonIds, true)
                        ? Lesson::find($lessonData['id'])
                        : new Lesson(['course_id' => $course->id]);

                    $isVideo = $lessonData['type'] === 'video';
                    $upload = $isVideo ? ($lessonData['video_path'] ?? null) : null;

                    $lesson->fill([
                        'course_section_id' => $section->id,
                        'title' => $lessonData['title'],
                        'type' => $lessonData['type'],
                        // An upload wins over a link; an article has neither.
                        'video_url' => $isVideo && ! $upload ? ($lessonData['video_url'] ?? null) : null,
                        'video_path' => $upload,
                        'video_name' => $upload ? ($lessonData['video_name'] ?? basename($upload)) : null,
                        'video_size' => $upload ? LessonVideos::disk()->size($upload) : null,
                        'body' => $lessonData['body'] ?? null,
                        'media_id' => $lessonData['media_id'] ?? null,
                        'duration_minutes' => (int) ($lessonData['duration_minutes'] ?? 0),
                        'is_preview' => (bool) ($lessonData['is_preview'] ?? false),
                        'position' => $l,
                    ])->save();
                    $keptLessons[] = $lesson->id;
                }
            }

            Lesson::where('course_id', $course->id)->whereNotIn('id', $keptLessons)->delete();
            CourseSection::where('course_id', $course->id)->whereNotIn('id', $keptSections)->delete();
        });

        // Videos replaced, removed, or on lessons that went: off the disk.
        $videosAfter = $course->lessons()->whereNotNull('video_path')->pluck('video_path')->all();
        foreach (array_diff($videosBefore, $videosAfter) as $path) {
            LessonVideos::delete($path);
        }
        LessonVideos::sweep($course->id, $videosAfter);

        // Adding or removing lessons changes who has finished the course.
        Enrollment::where('course_id', $course->id)->get()->each->syncCompletion();

        ActivityLog::record('Updated curriculum', $course->title, $course);

        return response()->json(['message' => 'Curriculum saved.', 'data' => $this->tree($course)]);
    }

    private function tree(Course $course): array
    {
        return $course->sections()->with('lessons.attachment')->get()->map(fn (CourseSection $section) => [
            'id' => $section->id,
            'title' => $section->title,
            'lessons' => $section->lessons->map(fn (Lesson $lesson) => [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'type' => $lesson->type,
                'video_url' => (string) $lesson->video_url,
                'video_path' => $lesson->video_path,
                'video_name' => $lesson->video_name,
                'video_size' => $lesson->video_size,
                'body' => (string) $lesson->body,
                'media_id' => $lesson->media_id,
                'attachment' => $lesson->attachment
                    ? ['id' => $lesson->attachment->id, 'name' => $lesson->attachment->name, 'url' => $lesson->attachment->url]
                    : null,
                'duration_minutes' => $lesson->duration_minutes,
                'is_preview' => $lesson->is_preview,
            ])->values(),
        ])->values()->all();
    }
}
