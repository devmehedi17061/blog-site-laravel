<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Support\Security;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Renders the single admin page. Screens are swapped client side by Alpine
 * through the hash, so there is one route rather than one per screen.
 */
class PanelController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user()->load('role.permissions');
        $security = app(Security::class);

        return view('backend.panel', [
            'panel' => [
                'user' => UserResource::make($user)->resolve(),
                // What the account owes before the rest of the panel opens;
                // the API enforces the same (EnsureAccountSecure).
                'owes' => match (true) {
                    $security->requiresTwoFactor($user) && ! $user->hasTwoFactor() => 'two_factor_required',
                    $security->passwordExpired($user) => 'password_expired',
                    default => null,
                },
                'recovery_notice' => session('recovery_notice'),
            ],
        ]);
    }
}
