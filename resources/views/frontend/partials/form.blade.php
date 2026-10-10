@php
  /**
   * @var \App\Models\Form $form
   * @var \Illuminate\Support\MessageBag $bag  this form's errors, kept apart
   *                                            from any other form on the page
   * @var bool   $replay   whether the old input belongs to this form
   * @var ?string $done    the thank-you line, set right after a successful post
   * @var bool   $hasUpload
   */
@endphp

<form class="cms-form" method="POST" action="{{ route('forms.submit', $form->slug) }}"
      @if ($hasUpload) enctype="multipart/form-data" @endif novalidate>
  @csrf

  @if ($done)
    <p class="cms-form-done" role="status">{{ __($done) }}</p>
  @endif

  {{-- A CAPTCHA problem is said beside the button, not counted as an answer. --}}
  @if ($bag->any() && ! ($bag->count() === 1 && $bag->has('captcha')))
    <p class="cms-form-alert" role="alert">
      {{ trans_choice('One answer needs fixing.|:count answers need fixing.', $bag->count(), ['count' => $bag->count()]) }}
    </p>
  @endif

  {{-- A field no person can see. Anything that fills it in is a bot, and the
       submission is dropped without telling it so. --}}
  <div class="cms-form-trap" aria-hidden="true">
    <label>Leave this field empty
      <input type="text" name="website" tabindex="-1" autocomplete="off" value="">
    </label>
  </div>

  {{-- When the form was drawn, encrypted. Sent back in under a few seconds
       means a script, not a person typing. --}}
  <input type="hidden" name="{{ \App\Support\SpamGuard::STAMP_FIELD }}" value="{{ app(\App\Support\SpamGuard::class)->stamp() }}">

  <div class="cms-form-grid">
    @foreach ($form->fields as $field)
      @include('frontend.partials.form-field', ['field' => $field, 'bag' => $bag, 'replay' => $replay])
    @endforeach
  </div>

  @if ($form->isAccepting())
    @if ($bag->has('captcha'))
      <p class="cms-error" role="alert">{{ $bag->first('captcha') }}</p>
    @endif
    @include('frontend.partials.captcha', ['action' => 'form'])
    <button type="submit" class="cms-form-submit">{{ __($form->submit_label ?: 'Submit') }}</button>
  @else
    <p class="cms-form-closed">{{ __('This form is not accepting replies at the moment.') }}</p>
  @endif
</form>
