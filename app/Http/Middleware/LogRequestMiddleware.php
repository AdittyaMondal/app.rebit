<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogRequestMiddleware
{
    /**
     * Request logging is a debugging aid, not an audit log. Anything here that
     * reaches storage/logs is a plaintext credential: bearer tokens for the
     * native app, the session cookie, and the password posted to any auth
     * endpoint. Redact at the source rather than trusting LOG_REQUESTS to stay
     * false.
     */
    private const SECRET_HEADERS = ['authorization', 'cookie', 'x-xsrf-token', 'x-csrf-token', 'proxy-authorization'];
    private const SECRET_COOKIES = ['*'];
    private const SECRET_INPUT   = ['password', 'password_confirmation', 'current_password', 'new_password', 'token', 'verifier', 'code', 'secret', 'client_secret', 'api_key', '_token'];

    public function handle(Request $request, Closure $next): Response
    {
        if(config('logging.log_requests')) {
            requests_log('Incoming request', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'referer' => $request->headers->get('referer'),
                'input' => $this->redact($request->all(), self::SECRET_INPUT),
                'headers' => $this->redact($request->headers->all(), self::SECRET_HEADERS),
                'cookies' => $this->redact($request->cookies->all(), self::SECRET_COOKIES),
                // Session is omitted entirely: it carries the auth guard payload
                // and CSRF token, and nothing in it is worth a credential leak.
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                ] : null,
                'created_at' => now()->toDateTimeString(),
            ]);
        }

        return $next($request);
    }

    /**
     * Replaces the value of any matching key with a marker. '*' redacts every
     * value, keeping only the key names, which is all a debugger needs.
     */
    private function redact(array $values, array $secretKeys): array
    {
        $redactAll = in_array('*', $secretKeys, true);

        foreach ($values as $key => $value) {
            if ($redactAll || in_array(strtolower((string) $key), $secretKeys, true)) {
                $values[$key] = '[redacted]';
            } elseif (is_array($value)) {
                // Recursive: a nested payload hides secrets just as well as a
                // flat one, and header values arrive as arrays.
                $values[$key] = $this->redact($value, $secretKeys);
            }
        }

        return $values;
    }
}
