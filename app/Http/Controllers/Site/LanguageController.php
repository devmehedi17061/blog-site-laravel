<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Middleware\SetSiteLanguage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** The বাংলা / EN switch: remembered for the visit, and on the account. */
class LanguageController extends Controller
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        abort_unless(array_key_exists($locale, SetSiteLanguage::OFFERED), 404);

        $request->session()->put('locale', $locale);
        $request->user()?->forceFill(['locale' => $locale])->saveQuietly();

        // Back where they were — but only to a page on this site.
        $back = url()->previous();

        return redirect()->to(str_starts_with($back, url('/')) ? $back : route('home'));
    }
}
