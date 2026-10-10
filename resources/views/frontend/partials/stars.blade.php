{{-- A rating out of five, drawn as stars. $value may be a decimal. --}}
<span class="stars" role="img" aria-label="{{ __('Rated :value out of 5', ['value' => number_format((float) $value, 1)]) }}">
  @for ($i = 1; $i <= 5; $i++)
    <span class="{{ $i <= round((float) $value) ? 'on' : '' }}" aria-hidden="true">★</span>
  @endfor
</span>
