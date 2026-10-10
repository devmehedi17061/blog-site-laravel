<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\QuizQuestion;
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
            // Quiz lessons.
            'sections.*.lessons.*.pass_mark' => ['nullable', 'integer', 'between:1,100'],
            'sections.*.lessons.*.max_attempts' => ['nullable', 'integer', 'between:0,100'],
            'sections.*.lessons.*.shuffle_questions' => ['nullable', 'boolean'],
            'sections.*.lessons.*.questions' => ['nullable', 'array', 'max:200'],
            'sections.*.lessons.*.questions.*.id' => ['nullable', 'integer'],
            'sections.*.lessons.*.questions.*.question' => ['required', 'string', 'max:2000'],
            'sections.*.lessons.*.questions.*.type' => ['required', Rule::in(QuizQuestion::TYPES)],
            'sections.*.lessons.*.questions.*.explanation' => ['nullable', 'string', 'max:2000'],
            'sections.*.lessons.*.questions.*.points' => ['nullable', 'integer', 'between:1,100'],
            'sections.*.lessons.*.questions.*.options' => ['required', 'array', 'min:2', 'max:10', function ($attribute, $options, $fail) use ($request) {
                $type = data_get($request->all(), str_replace('.options', '.type', $attribute));
                $right = collect($options)->filter(fn ($o) => ! empty($o['correct']))->count();

                if (collect($options)->contains(fn ($o) => trim((string) ($o['text'] ?? '')) === '')) {
                    $fail('Every answer option needs text.');
                } elseif ($right === 0) {
                    $fail('Mark the right answer.');
                } elseif ($type !== 'multiple' && $right > 1) {
                    $fail('A single-answer question has exactly one right answer.');
                }
            }],
        ], [
            'sections.*.title.required' => 'Every section needs a title.',
            'sections.*.lessons.*.title.required' => 'Every lesson needs a title.',
            'sections.*.lessons.*.questions.*.question.required' => 'Every quiz question needs its question.',
            'sections.*.lessons.*.questions.*.options.min' => 'A question needs at least two answers.',
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
                        'pass_mark' => (int) ($lessonData['pass_mark'] ?? 70),
                        'max_attempts' => (int) ($lessonData['max_attempts'] ?? 0),
                        'shuffle_questions' => (bool) ($lessonData['shuffle_questions'] ?? false),
                        'position' => $l,
                    ])->save();
                    $keptLessons[] = $lesson->id;

                    $this->saveQuestions($lesson, $lessonData['type'] === 'quiz' ? ($lessonData['questions'] ?? []) : []);
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

        // Removing lessons can finish the course for someone (finishing is
        // never undone by adding them).
        Enrollment::where('course_id', $course->id)->get()->each->syncCompletion();

        ActivityLog::record('Updated curriculum', $course->title, $course);

        return response()->json(['message' => 'Curriculum saved.', 'data' => $this->tree($course)]);
    }

    private function tree(Course $course): array
    {
        return $course->sections()->with(['lessons.attachment', 'lessons.questions'])->get()->map(fn (CourseSection $section) => [
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
                'pass_mark' => $lesson->pass_mark,
                'max_attempts' => $lesson->max_attempts,
                'shuffle_questions' => $lesson->shuffle_questions,
                'questions' => $lesson->questions->map(fn (QuizQuestion $q) => [
                    'id' => $q->id,
                    'question' => $q->question,
                    'type' => $q->type,
                    'options' => collect($q->options)->map(fn ($o) => ['text' => (string) $o['text'], 'correct' => (bool) ($o['correct'] ?? false)])->values(),
                    'explanation' => (string) $q->explanation,
                    'points' => $q->points,
                ])->values(),
            ])->values(),
        ])->values()->all();
    }

    /**
     * A quiz lesson's questions, replaced as a list like the lessons are:
     * existing ones keep their ids (so past attempts still point at them),
     * missing ones go. A lesson that is no longer a quiz loses them all.
     */
    private function saveQuestions(Lesson $lesson, array $questions): void
    {
        $existing = $lesson->questions()->pluck('id')->all();
        $kept = [];

        foreach (array_values($questions) as $i => $data) {
            $question = in_array((int) ($data['id'] ?? 0), $existing, true)
                ? QuizQuestion::find($data['id'])
                : new QuizQuestion(['lesson_id' => $lesson->id]);

            $options = collect($data['options'])->map(fn ($o) => [
                'text' => trim((string) $o['text']),
                'correct' => (bool) ($o['correct'] ?? false),
            ])->values()->all();

            $question->fill([
                'question' => $data['question'],
                'type' => $data['type'],
                'options' => $options,
                'explanation' => $data['explanation'] ?? null,
                'points' => (int) ($data['points'] ?? 1),
                'position' => $i,
            ])->save();

            $kept[] = $question->id;
        }

        $lesson->questions()->whereNotIn('id', $kept ?: [0])->delete();
    }
}
