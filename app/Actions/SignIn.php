<?php

namespace App\Actions;

use App\Models\ActivityLog;
use App\Models\User;
use App\Support\Security;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The last step of every sign-in, whether it took one step or two.
 */
class SignIn
{
    public function __construct(private readonly Security $security) {}

    public function complete(Request $request, User $user, bool $remember, string $how = 'password'): void
    {
        // One session per person: whatever else they had open ends now.
        // The remember-me token is changed as well, or a browser elsewhere
        // with "keep me signed in" would walk straight back in.
        if ($this->security->get('single_session')) {
            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            }
            $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();
        }

        Auth::login($user, $remember);
        $request->session()->regenerate();
        $request->session()->forget('login.pending');

        $user->forceFill(['last_seen_at' => now()])->saveQuietly();

        ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'Signed in',
            'description' => $user->name.' signed in'.($how === 'password' ? '' : ' with '.$how),
            'ip_address' => $request->ip(),
        ]);
    }
}
