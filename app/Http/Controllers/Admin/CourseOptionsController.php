<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Course;
use App\Models\Role;
use App\Models\User;
use App\Support\Lms;
use Illuminate\Http\JsonResponse;

/** The course editor's select boxes, in one request. */
class CourseOptionsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $this->authorize('viewAny', Course::class);

        return response()->json([
            'statuses' => Course::STATUSES,
            'levels' => Course::LEVELS,
            'categories' => Category::flat()->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'depth' => $category->depth,
                'path' => $category->path(),
            ])->values(),
            // Anyone who works on the site may teach; students may not.
            'instructors' => User::where('status', 'Active')
                ->whereHas('role', fn ($q) => $q->where('slug', '!=', Role::STUDENT))
                ->orderBy('name')->get(['id', 'name']),
            'currency' => Lms::setting('currency'),
        ]);
    }
}
