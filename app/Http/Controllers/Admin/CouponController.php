<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CouponResource;
use App\Models\ActivityLog;
use App\Models\Coupon;
use App\Models\Course;
use App\Support\Lms;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/** LMS → Coupons. */
class CouponController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Coupon::class);

        $request->validate([
            'search' => ['nullable', 'string', 'max:60'],
            'per_page' => ['nullable', 'integer', 'between:5,100'],
        ]);

        $search = trim((string) $request->input('search'));
        $status = $request->input('status');

        $coupons = Coupon::query()
            ->with('courses:id,title')
            ->withCount('redemptions')
            ->withSum('redemptions', 'discount')
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('code', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%")))
            ->when($status === 'Active', fn ($q) => $q->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now())))
            ->when($status === 'Expired', fn ($q) => $q->whereNotNull('expires_at')->where('expires_at', '<=', now()))
            ->when($status === 'Off', fn ($q) => $q->where('is_active', false))
            ->latest()
            ->paginate((int) ($request->input('per_page') ?: 10))
            ->withQueryString();

        return CouponResource::collection($coupons);
    }

    public function options(): JsonResponse
    {
        $this->authorize('viewAny', Coupon::class);

        return response()->json([
            'statuses' => ['Active', 'Expired', 'Off'],
            'types' => Coupon::TYPES,
            'courses' => Course::orderBy('title')->get(['id', 'title', 'price', 'sale_price'])
                ->map(fn (Course $c) => ['id' => $c->id, 'title' => $c->title, 'price' => Lms::money($c->currentPrice())]),
            'currency' => Lms::setting('currency'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Coupon::class);

        $data = $this->validated($request);
        $coupon = Coupon::create($data + ['created_by' => $request->user()->id]);
        $coupon->courses()->sync($data['course_ids'] ?? []);

        ActivityLog::record('Created coupon', $coupon->code.' · '.$coupon->label(), $coupon);

        return CouponResource::make($coupon->load('courses:id,title'))->response()->setStatusCode(201);
    }

    public function show(Coupon $coupon): CouponResource
    {
        $this->authorize('view', $coupon);

        return CouponResource::make($coupon->load('courses:id,title')->loadCount('redemptions')->loadSum('redemptions', 'discount'));
    }

    public function update(Request $request, Coupon $coupon): CouponResource
    {
        $this->authorize('update', $coupon);

        $data = $this->validated($request, $coupon);
        $coupon->update($data);
        $coupon->courses()->sync($data['course_ids'] ?? []);

        ActivityLog::record('Updated coupon', $coupon->code, $coupon);

        return CouponResource::make($coupon->load('courses:id,title')->loadCount('redemptions')->loadSum('redemptions', 'discount'));
    }

    public function destroy(Coupon $coupon): JsonResponse
    {
        $this->authorize('delete', $coupon);

        // Payments that used it keep their code and discount as text.
        $coupon->delete();
        ActivityLog::record('Deleted coupon', $coupon->code);

        return response()->json(['message' => 'Coupon deleted.']);
    }

    private function validated(Request $request, ?Coupon $coupon = null): array
    {
        $request->merge([
            'code' => Coupon::normalise($request->input('code')),
            // Empty boxes in the form mean "no limit".
            'max_uses' => $request->input('max_uses') === '' ? null : $request->input('max_uses'),
            'min_amount' => $request->input('min_amount') === '' ? null : $request->input('min_amount'),
            'starts_at' => $request->input('starts_at') ?: null,
            'expires_at' => $request->input('expires_at') ?: null,
        ]);

        return $request->validate([
            'code' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('coupons', 'code')->ignore($coupon?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in(Coupon::TYPES)],
            'value' => ['required', 'numeric', 'gt:0', $request->input('type') === 'percent' ? 'max:100' : 'max:9999999'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'max_uses_per_user' => ['required', 'integer', 'min:0', 'max:1000'],
            'min_amount' => ['nullable', 'numeric', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['required', 'boolean'],
            'course_ids' => ['nullable', 'array'],
            'course_ids.*' => ['integer', 'exists:courses,id'],
        ], [
            'code.regex' => 'Use capital letters, numbers, - and _ only.',
            'value.max' => 'A percentage cannot be more than 100.',
            'expires_at.after' => 'The end must come after the start.',
        ]);
    }
}
