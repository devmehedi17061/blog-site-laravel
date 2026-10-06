<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Support\LessonVideos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Receives an uploaded lesson video, one chunk per request (see
 * App\Support\LessonVideos). The finished file is not attached to anything
 * yet: the curriculum save that follows puts its path on a lesson.
 */
class LessonVideoController extends Controller
{
    public function store(Request $request, Course $course): JsonResponse
    {
        $this->authorize('update', $course);

        $data = $request->validate([
            'upload_id' => ['required', 'string', 'regex:/^[A-Za-z0-9]{16,40}$/'],
            'index' => ['required', 'integer', 'min:0'],
            'total' => ['required', 'integer', 'min:1', 'max:5000', 'gt:index'],
            'name' => ['required', 'string', 'max:255'],
            // A little over the chunk size the browser sends.
            'chunk' => ['required', 'file', 'max:'.(int) ceil(LessonVideos::CHUNK_BYTES / 1024 * 1.2)],
        ]);

        try {
            $video = LessonVideos::receive(
                $course->id, $data['upload_id'], (int) $data['index'], (int) $data['total'], $data['name'], $request->file('chunk')
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['chunk' => [$e->getMessage()]]], 422);
        }

        return response()->json($video ? ['done' => true, 'video' => $video] : ['done' => false]);
    }
}
