<?php

namespace App\Http\Controllers\Admin;

use App\Actions\SignIn;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LoginRequest;
use App\Models\ActivityLog;
use App\Support\TwoFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Throwable;

class LoginController extends Controller
{
    public function show(): View
    {
        return view('backend.login');
    }

    /**
     * The right password signs someone in — unless they use two-factor, in
     * which case it only gets them to the second step. Who they are is held
     * in the session, for ten minutes, and nothing else is granted until the
     * code checks out.
     */
    public function store(LoginRequest $request, SignIn $signIn): RedirectResponse
    {
        $user = $request->authenticatedUser();

        if (! $user->hasTwoFactor()) {
            $signIn->complete($request, $user, $request->boolean('remember'));

            return redirect()->intended(route('admin.panel'));
        }

        $request->session()->regenerate();
        $request->session()->put('login.pending', [
            'user_id' => $user->id,
            'remember' => $request->boolean('remember'),
            'expires' => now()->addMinutes(10)->timestamp,
            'tries' => 0,
        ]);

        if ($user->two_factor_method === 'email') {
            try {
                TwoFactor::sendEmailCode($user);
            } catch (Throwable $e) {
                report($e);

                return redirect()->route('admin.two-factor')
                    ->withErrors(['code' => 'The code email could not be sent. Use a recovery code, or ask an admin to check Settings → Email.']);
            }
        }

        return redirect()->route('admin.two-factor');
    }

    public function destroy(Request $request): RedirectResponse
    {
        if ($user = $request->user()) {
            ActivityLog::create([
                'user_id' => $user->id,
                'action' => 'Signed out',
                'description' => $user->name.' signed out',
                'ip_address' => $request->ip(),
            ]);
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
