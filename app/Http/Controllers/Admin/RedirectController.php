<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Redirect;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Settings → Redirects: the automatic ones (a slug changed) and any added by
 * hand (links from an old site, a page retired in favour of another).
 */
class RedirectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('settings.view'), 403);

        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'source' => ['nullable', Rule::in(['all', 'auto', 'manual'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $page = Redirect::query()
            ->when(($data['source'] ?? 'all') !== 'all', fn ($q) => $q->where('source', $data['source']))
            ->when($data['search'] ?? null, fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('from_path', 'like', "%{$term}%")->orWhere('to_path', 'like', "%{$term}%")))
            ->latest('id')
            ->paginate(25);

        return response()->json([
            'data' => $page->getCollection()->map(fn (Redirect $r) => [
                'id' => $r->id,
                'from' => $r->from_path,
                'to' => $r->to_path,
                'status' => $r->status,
                'source' => $r->source,
                'note' => $r->note,
                'hits' => $r->hits,
                'last_hit' => $r->last_hit_at?->diffForHumans(),
                'created' => $r->created_at?->format('j M Y'),
            ]),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('settings.update'), 403);

        $data = $request->validate([
            'from' => ['required', 'string', 'max:500', 'regex:#^/#', 'not_regex:#^/admin(/|$)#i'],
            'to' => ['required', 'string', 'max:500', 'regex:#^(/|https?://)#i'],
            'status' => ['required', Rule::in(Redirect::STATUSES)],
            'note' => ['nullable', 'string', 'max:190'],
        ], [
            'from.regex' => 'Start with / — the part of the address after the domain, like /old-page.',
            'from.not_regex' => 'The admin panel cannot be redirected.',
            'to.regex' => 'Start with / for a page on this site, or https:// for another site.',
        ]);

        $from = Redirect::normalise($data['from']);
        $to = preg_match('#^https?://#i', $data['to']) ? $data['to'] : Redirect::normalise($data['to']);

        if ($from === '/') {
            return $this->fail('from', 'The homepage itself cannot be redirected.');
        }
        if ($from === $to) {
            return $this->fail('to', 'An address cannot redirect to itself.');
        }
        if (Redirect::where('from_path', $from)->exists()) {
            return $this->fail('from', 'There is already a redirect from that address.');
        }
        if (! str_starts_with($to, 'http') && Redirect::leadsBackTo($to, $from)) {
            return $this->fail('to', 'That would make a loop: following it leads back here.');
        }

        $redirect = Redirect::create(['from_path' => $from, 'to_path' => $to, 'status' => (int) $data['status'], 'source' => 'manual', 'note' => $data['note'] ?? null]);
        ActivityLog::record('Added redirect', "{$from} → {$to}");

        return response()->json(['message' => 'Redirect added.', 'id' => $redirect->id], 201);
    }

    public function destroy(Request $request, Redirect $redirect): JsonResponse
    {
        abort_unless($request->user()->hasPermission('settings.update'), 403);

        $redirect->delete();
        ActivityLog::record('Removed redirect', "{$redirect->from_path} → {$redirect->to_path}");

        return response()->json(['message' => 'Redirect removed.']);
    }

    private function fail(string $field, string $message): JsonResponse
    {
        return response()->json(['message' => $message, 'errors' => [$field => [$message]]], 422);
    }
}
