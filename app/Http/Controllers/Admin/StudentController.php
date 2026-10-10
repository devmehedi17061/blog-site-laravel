<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Role;
use App\Models\User;
use App\Support\CsvExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Learner accounts at a glance: what they are on and how far they got.
 * Editing an account (name, password, suspending it) stays in User
 * management, which this screen links to.
 */
class StudentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Enrollment::class);

        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'between:5,100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $own = $request->user()->role?->scope === 'Own content';
        $mine = fn (Builder $q) => $own ? $q->whereHas('course', fn (Builder $c) => $c->where('user_id', $request->user()->id)) : $q;

        $students = User::query()
            ->whereHas('role', fn (Builder $q) => $q->where('slug', Role::STUDENT))
            // An instructor sees only the students on their own courses.
            ->when($own, fn (Builder $q) => $q->whereHas('enrollments', $mine))
            ->search($data['search'] ?? null)
            ->withCount([
                'enrollments as active_count' => fn (Builder $q) => $mine($q->where('status', 'Active')),
                'enrollments as pending_count' => fn (Builder $q) => $mine($q->where('status', 'Pending')),
                'enrollments as completed_count' => fn (Builder $q) => $mine($q->whereNotNull('completed_at')),
            ])
            ->latest()
            ->paginate((int) ($data['per_page'] ?? 10))
            ->withQueryString();

        return response()->json([
            'data' => $students->getCollection()->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'initials' => $u->initials,
                'status' => $u->status,
                'active_count' => (int) $u->active_count,
                'pending_count' => (int) $u->pending_count,
                'completed_count' => (int) $u->completed_count,
                'joined' => $u->created_at?->format('d M Y'),
                'last_seen' => $u->last_seen_at?->diffForHumans() ?? 'Never',
            ])->values(),
            'meta' => [
                'current_page' => $students->currentPage(),
                'last_page' => $students->lastPage(),
                'from' => $students->firstItem() ?? 0,
                'to' => $students->lastItem() ?? 0,
                'total' => $students->total(),
            ],
            'can_manage' => $request->user()->hasPermission('users.update'),
        ]);
    }

    /** Every student this account may see, as a CSV file. */
    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Enrollment::class);
        $own = $request->user()->role?->scope === 'Own content';

        $rows = User::query()
            ->whereHas('role', fn (Builder $q) => $q->where('slug', Role::STUDENT))
            ->when($own, fn (Builder $q) => $q->whereHas('enrollments', fn (Builder $e) => $e->whereHas('course', fn (Builder $c) => $c->where('user_id', $request->user()->id))))
            ->withCount([
                'enrollments as active_count' => fn (Builder $q) => $q->where('status', 'Active'),
                'enrollments as completed_count' => fn (Builder $q) => $q->whereNotNull('completed_at'),
                'enrollments as pending_count' => fn (Builder $q) => $q->where('status', 'Pending'),
            ])
            ->orderBy('name')
            ->lazy(200)
            ->map(fn (User $u) => [
                $u->name, $u->email, $u->phone, $u->status, $u->active_count, $u->completed_count, $u->pending_count,
                $u->created_at?->format('Y-m-d'), $u->last_seen_at?->format('Y-m-d H:i'),
            ]);

        return CsvExport::download('students', [
            'Name', 'Email', 'Phone', 'Status', 'Courses (active)', 'Completed', 'Awaiting payment', 'Joined', 'Last seen',
        ], $rows);
    }
}
