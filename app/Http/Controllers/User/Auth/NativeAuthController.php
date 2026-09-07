<?php

namespace App\Http\Controllers\User\Auth;

use App\Enums\User\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\Http\Api\SupportsApiResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Hands a Sanctum token to the native app without the app ever seeing a password.
 *
 * The app opens /auth/native/start in the system browser. Everything after that
 * is the ordinary web auth stack -- Livewire login, Google, signup, onboarding,
 * password reset -- completely unmodified. They all finish at
 * route('user.desktop.index'), where NativeAuthHandoffMiddleware notices the
 * session flag and routes here.
 *
 * The handoff uses PKCE. What crosses the custom URL scheme is a single-use code
 * bound to the SHA-256 of a verifier that never leaves the device, so a hijacked
 * scheme yields nothing. The token itself only ever travels over HTTPS in a POST
 * response body.
 */
class NativeAuthController extends Controller
{
    use SupportsApiResponses;

    private const SESSION_KEY = 'native_auth';

    /**
     * Entry point. Deliberately outside both the `guest` and `auth` middleware:
     * an already-signed-in browser short-circuits straight to complete(), and a
     * guest is sent to login by the app's own redirectGuestsTo().
     */
    public function start(Request $request)
    {
        $data = $request->validate([
            'challenge' => ['required', 'string', 'size:43', 'regex:/^[A-Za-z0-9\-_]+$/'],
            'state' => ['required', 'string', 'size:43', 'regex:/^[A-Za-z0-9\-_]+$/'],
        ]);

        $request->session()->put(self::SESSION_KEY, [
            'challenge' => $data['challenge'],
            'state' => $data['state'],
            'at' => now()->timestamp,
        ]);

        if (auth_check() && me()->status === UserStatus::ACTIVE) {
            return redirect()->route('user.auth.native.complete');
        }

        return redirect()->route('user.auth.index');
    }

    /**
     * Reached once the user is authenticated and ACTIVE, either directly from
     * start() or via the handoff middleware after any of the web auth flows.
     */
    public function complete(Request $request)
    {
        $handoff = $request->session()->pull(self::SESSION_KEY);

        abort_if(! $handoff, 419);
        abort_if(now()->timestamp - $handoff['at'] > config('native.handoff_ttl'), 419);
        abort_if(me()->status !== UserStatus::ACTIVE, 403);

        $code = Str::random(43);

        Cache::put(
            $this->cacheKey($code),
            ['user_id' => me()->id, 'challenge' => $handoff['challenge']],
            now()->addSeconds(config('native.code_ttl'))
        );

        return redirect()->away(sprintf(
            '%s://auth?code=%s&state=%s',
            config('native.scheme'),
            $code,
            $handoff['state']
        ));
    }

    /**
     * Exchanges the one-time code for a token. Unauthenticated by design -- the
     * code plus the PKCE verifier are the credential.
     */
    public function exchange(Request $request)
    {
        $input = $request->validate([
            'code' => ['required', 'string', 'size:43'],
            'verifier' => ['required', 'string', 'min:43', 'max:128'],
            'device_name' => ['required', 'string', 'max:64'],
        ]);

        // pull(), not get(): single use, even if the exchange itself then fails.
        $entry = Cache::pull($this->cacheKey($input['code']));

        if (! $entry || ! hash_equals($entry['challenge'], $this->challengeFor($input['verifier']))) {
            return $this->responseError(['message' => __('auth.failed')], 401);
        }

        $user = User::find($entry['user_id']);

        if (! $user || $user->status !== UserStatus::ACTIVE) {
            return $this->responseError(['message' => __('auth.failed')], 401);
        }

        $token = $user->createToken(
            'native: ' . $input['device_name'],
            ['*'],
            now()->addDays(config('native.token_days'))
        );

        return $this->responseSuccess([
            'data' => [
                'token' => $token->plainTextToken,
                'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            ],
        ]);
    }

    private function cacheKey(string $code): string
    {
        // The code is a credential, so it is hashed before it becomes a cache
        // key -- cache keys turn up in logs and in Redis' keyspace.
        return 'native_auth:' . hash('sha256', $code);
    }

    private function challengeFor(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }
}
