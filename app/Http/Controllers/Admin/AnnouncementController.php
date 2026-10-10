<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Course;
use App\Models\CourseAnnouncement;
use App\Models\User;
use App\Notifications\StudentNotice;
use App\Support\Lms;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * News for everyone on a course, from the course editor's Announcements
 * tab. Each student gets it in their Notifications, and it shows on the
 * course page for students on it.
 */
class AnnouncementController extends Controller
{
    public function index(Course $course): JsonResponse
    {
        $this->authorize('view', $course);

        return response()->json([
            'data' => $course->announcements()->with('author:id,name')->get()->map(fn (CourseAnnouncement $a) => $this->row($a)),
            'students' => $course->enrollments()->where('status', 'Active')->count(),
        ]);
    }

    public function store(Request $request, Course $course): JsonResponse
    {
        $this->authorize('update', $course);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $announcement = $course->announcements()->create($data + ['user_id' => $request->user()->id]);

        $students = User::whereIn('id', $course->enrollments()->where('status', 'Active')->pluck('user_id'))->get();
        foreach ($students as $student) {
            Lms::notify($student, new StudentNotice(
                $course->title.': '.$data['title'],
                Str::limit($data['body'], 240),
                route('courses.show', $course->slug),
                'info',
                'Open the course',
                email: false,
            ));
        }

        ActivityLog::record('Posted announcement', $course->title.' · '.$data['title'], $course);

        return response()->json([
            'message' => 'Announcement sent to '.$students->count().' '.Str::plural('student', $students->count()).'.',
            'data' => $this->row($announcement->load('author:id,name')),
        ], 201);
    }

    public function destroy(Course $course, CourseAnnouncement $announcement): JsonResponse
    {
        $this->authorize('update', $course);
        abort_unless($announcement->course_id === $course->id, 404);

        $announcement->delete();

        return response()->json(['message' => 'Announcement deleted. Notifications already sent stay with the students.']);
    }

    private function row(CourseAnnouncement $a): array
    {
        return [
            'id' => $a->id,
            'title' => $a->title,
            'body' => $a->body,
            'author' => $a->author?->name,
            'date' => $a->created_at?->format('d M Y, H:i'),
        ];
    }
}
