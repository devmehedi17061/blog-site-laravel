{{-- বাংলা / EN: the header's {{ language }} token, or the corner switch. --}}
<nav class="lang-switch" aria-label="{{ __('Language') }}">
  @foreach (\App\Http\Middleware\SetSiteLanguage::OFFERED as $code => $label)
    <a href="{{ route('language.switch', $code) }}" hreflang="{{ $code }}" lang="{{ $code }}"
       class="{{ app()->getLocale() === $code ? 'is-on' : '' }}" @if (app()->getLocale() === $code) aria-current="true" @endif>
      {{ $code === 'en' ? 'EN' : $label }}
    </a>
  @endforeach
</nav>
