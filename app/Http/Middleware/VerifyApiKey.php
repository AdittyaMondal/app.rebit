<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Traits\Http\Api\SupportsApiResponses;
use Symfony\Component\HttpFoundation\Response;

class VerifyApiKey
{
    use SupportsApiResponses;

    public function handle(Request $request, Closure $next): Response
    {
        $provided = (string) $request->header('X-API-KEY');
        $expected = (string) config('app.api_key');

        // hash_equals: constant-time, so a wrong key can't be recovered by timing.
        if ($expected === '' || ! hash_equals($expected, $provided)) {
            return $this->responseUnauthorizedError([
                'message' => 'API key is invalid or not provided.'
            ]);
        }

        return $next($request);
    }
}
