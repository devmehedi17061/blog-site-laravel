{{-- The drop-down arrow beside a header menu item that has a sub-menu, in the
     style chosen for the header (SitePart::CARETS). Also drawn in the admin's
     picker, so the choice there looks exactly like the site. @var string $style --}}
@switch($style)
  @case('chevron')
    <svg width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.5 4.5 6 8l3.5-3.5"/></svg>
    @break
  @case('plus')
    <svg width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><path d="M6 2.5v7M2.5 6h7"/></svg>
    @break
  @case('arrow')
    <svg width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 2v7.5M2.8 6.3 6 9.5l3.2-3.2"/></svg>
    @break
  @case('triangle')
    <svg width="12" height="12" viewBox="0 0 12 12" fill="currentColor" aria-hidden="true"><path d="M2.5 4.5h7L6 8.5z"/></svg>
    @break
@endswitch
