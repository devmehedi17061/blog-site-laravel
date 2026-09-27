<?php

namespace App\Http\Middleware;

use App\Support\Security;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Two things an account can owe before it may use the panel:
 *
 *   two-factor, when its role requires it and it is not set up yet;
 *   a new password, when the old one is past the expiry set in Security.
 *
 * Until then every panel API call is refused except My account — where
 * both get sorted — so the gate cannot be walked round by calling the API
 * directly. The panel reads the same answer and opens My account.
 */
class EnsureAccountSecure
{
    public function __construct(private readonly Security $security) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $request->routeIs('admin.api.account.*')) {
            return $next($request);
        }

        $owes = match (true) {
            $this->security->requiresTwoFactor($user) && ! $user->hasTwoFactor() => 'two_factor_required',
            $this->security->passwordExpired($user) => 'password_expired',
            default => null,
        };

        if ($owes === null) {
            return $next($request);
        }

        return response()->json([
            'message' => $owes === 'two_factor_required'
                ? 'Your role requires two-factor sign-in. Set it up under My account to continue.'
                : 'Your password has expired. Choose a new one under My account to continue.',
            'code' => $owes,
        ], 403);
    }
}
