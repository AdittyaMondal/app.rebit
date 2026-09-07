<?php

namespace App\Http\Middleware;

use App\Enums\User\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The one hook that catches every web auth flow without modifying any of them.
 *
 * Livewire login, Google, signup -> confirm -> onboarding, and password reset
 * all finish by redirecting to route('user.desktop.index'). Rather than editing
 * each of those upstream files -- which would have to be re-merged on every
 * ColibriPlus release -- this watches for an authenticated request carrying the
 * handoff flag and diverts it to the code-minting route.
 *
 * On the web this is inert: the session key is written by exactly one route,
 * which only the native app ever calls.
 *
 * Deliberately not redirect()->intended(): Laravel already populates
 * url.intended for web guests who deep-link somewhere, so switching Login.php to
 * intended() would change where existing web users land after signing in.
 */
class NativeAuthHandoffMiddleware
{
    public const SESSION_KEY = 'native_auth';

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldHandOff($request)) {
            return redirect()->route('user.auth.native.complete');
        }

        return $next($request);
    }

    private function shouldHandOff(Request $request): bool
    {
        if (! $request->hasSession() || ! $request->session()->has(self::SESSION_KEY)) {
            return false;
        }

        // Only a top-level navigation can follow a custom-scheme redirect.
        // Handing one to a Livewire or fetch call would just break the caller.
        if (! $request->isMethod('GET') || $request->expectsJson() || $request->hasHeader('X-Livewire')) {
            return false;
        }

        // Would otherwise loop on complete() itself.
        if ($request->routeIs('user.auth.native.*')) {
            return false;
        }

        // ONBOARDING must be allowed to run to completion in the browser tab
        // first -- Username.php is the step that flips the user to ACTIVE.
        return auth_check() && me()->status === UserStatus::ACTIVE;
    }
}
