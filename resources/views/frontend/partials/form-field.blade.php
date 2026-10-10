@php
  /**
   * One field of a built form.
   *
   * @var \App\Models\FormField $field
   * @var \Illuminate\Support\MessageBag $bag
   * @var bool $replay
   */
  $key = 'fields.'.$field->name;
  $name = 'fields['.$field->name.']';
  $id = 'f'.$field->form_id.'-'.$field->name;
  $error = $bag->first($key) ?: $bag->first($key.'.*');
  $old = $replay ? old($key) : null;
  $describedBy = trim(($field->help ? $id.'-help ' : '').($error ? $id.'-error' : ''));
@endphp

<div class="cms-field is-{{ $field->width === 'half' ? 'half' : 'full' }} @if ($error) has-error @endif">

  @if ($field->isToggle())
    {{-- A lone checkbox carries its own label, beside the box. --}}
    <label class="cms-check" for="{{ $id }}">
      <input type="checkbox" id="{{ $id }}" name="{{ $name }}" value="1"
             @checked($old)
             @if ($field->required) required @endif
             @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif>
      <span>{{ __($field->label) }}@if ($field->required)<span class="cms-required" aria-hidden="true">*</span>@endif</span>
    </label>
  @else
    <label class="cms-label" for="{{ $id }}">
      {{ __($field->label) }}
      @if ($field->required)<span class="cms-required" aria-hidden="true">*</span>@endif
    </label>
  @endif

  @if ($field->type === 'textarea')
    <textarea class="cms-input" id="{{ $id }}" name="{{ $name }}" rows="5"
              placeholder="{{ $field->placeholder ? __($field->placeholder) : '' }}"
              @if ($field->required) required @endif
              @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif>{{ $old }}</textarea>

  @elseif ($field->type === 'select')
    <select class="cms-input" id="{{ $id }}" name="{{ $name }}"
            @if ($field->required) required @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif>
      <option value="">{{ __($field->placeholder ?: 'Choose…') }}</option>
      @foreach ($field->choices() as $choice)
        <option value="{{ $choice }}" @selected($old === $choice)>{{ __($choice) }}</option>
      @endforeach
    </select>

  @elseif ($field->type === 'radio')
    <div class="cms-choices" role="radiogroup" aria-label="{{ __($field->label) }}">
      @foreach ($field->choices() as $index => $choice)
        <label class="cms-check" for="{{ $id }}-{{ $index }}">
          <input type="radio" id="{{ $id }}-{{ $index }}" name="{{ $name }}" value="{{ $choice }}"
                 @checked($old === $choice)>
          <span>{{ __($choice) }}</span>
        </label>
      @endforeach
    </div>

  @elseif ($field->isMultiple())
    <div class="cms-choices">
      @foreach ($field->choices() as $index => $choice)
        <label class="cms-check" for="{{ $id }}-{{ $index }}">
          <input type="checkbox" id="{{ $id }}-{{ $index }}" name="fields[{{ $field->name }}][]" value="{{ $choice }}"
                 @checked(is_array($old) && in_array($choice, $old, true))>
          <span>{{ __($choice) }}</span>
        </label>
      @endforeach
    </div>

  @elseif ($field->type === 'file')
    <input type="file" class="cms-input cms-file" id="{{ $id }}" name="{{ $name }}"
           @if ($field->required) required @endif
           @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif>

  @elseif (! $field->isToggle())
    <input type="{{ $field->type }}" class="cms-input" id="{{ $id }}" name="{{ $name }}"
           value="{{ $old }}" placeholder="{{ $field->placeholder ? __($field->placeholder) : '' }}"
           @if ($field->required) required @endif
           @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif>
  @endif

  @if ($field->help)
    <p class="cms-help" id="{{ $id }}-help">{{ __($field->help) }}</p>
  @endif

  @if ($error)
    <p class="cms-error" id="{{ $id }}-error">{{ $error }}</p>
  @endif
</div>
