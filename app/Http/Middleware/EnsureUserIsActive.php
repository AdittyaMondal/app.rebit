<?php

namespace App\Http\Middleware;

use App\Enums\User\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API-stack counterpart to UserStatusMiddleware.
 *
 * UserStatusMiddleware cannot be reused here: for ONBOARDING it issues a
 * redirect to an HTML page, which is meaningless to an API client. Without
 * something on the API stack, a user blocked or suspended after login keeps
 * full API access -- on web until their session dies, and for a native client
 * until its bearer token expires.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        // Resolved through the sanctum guard explicitly, not the default one.
        // This middleware sits in the api group, which runs before the route's
        // auth:sanctum, so the default guard is still the session guard and would
        // miss bearer-authenticated native requests entirely.
        $user = $request->user('sanctum');

        if ($user) {
            if ($user->status === UserStatus::BLOCKED || $user->status === UserStatus::SUSPENDED) {
                return response()->json([
                    'status' => 'error',
                    'code' => Response::HTTP_FORBIDDEN,
                    'reason' => $user->status->value,
                    'message' => __('errors.account_unavailable'),
                ], Response::HTTP_FORBIDDEN);
            }

            if ($user->status === UserStatus::ONBOARDING) {
                return response()->json([
                    'status' => 'error',
                    'code' => Response::HTTP_CONFLICT,
                    'reason' => $user->status->value,
                    'message' => __('errors.onboarding_incomplete'),
                ], Response::HTTP_CONFLICT);
            }
        }

        return $next($request);
    }
}
