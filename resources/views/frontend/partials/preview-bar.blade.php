@php
  /** @var \App\Models\Page $page */
  $state = match (true) {
      $page->status === 'Draft' => 'a draft',
      ! $page->isLive() => 'scheduled for '.$site->date($page->published_at),
      default => 'live',
  };
@endphp

{{-- Inline styles on purpose: a landing page may carry none of the site's
     chrome, and this bar must look the same on every layout. --}}
<div role="status"
     style="display:flex;flex-wrap:wrap;align-items:center;gap:6px 14px;padding:9px 16px;background:#201e1d;color:#fff;font:600 13px/1.4 Archivo,system-ui,sans-serif">
  <span style="padding:2px 8px;background:#ec3013;font-size:11px;letter-spacing:.08em;text-transform:uppercase">Preview</span>
  <span>This page is {{ $state }}. Only people signed in to the panel can see this view.</span>
  <a href="{{ url('/admin#/pages/'.$page->id) }}" style="margin-left:auto;color:#ffc4b8">Back to the editor →</a>
</div>
