<?php

namespace App\Http\Controllers\Api\System;

use App\Http\Controllers\Controller;
use App\Support\BackendEmbeds;
use App\Traits\Http\Api\SupportsApiResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

/**
 * Serves the window.BackendEmbeds payload to clients that cannot receive it
 * inlined by Blade — i.e. the native app, which boots from a bundled shell.
 *
 * The route carries no auth middleware, but the payload is per-user for theme
 * and locale, so an optional bearer token is resolved by hand. me() and
 * auth_check() read the *default* guard, which on an unguarded route is the
 * session guard — hence the explicit Auth::setUser().
 */
class EmbedsController extends Controller
{
    use SupportsApiResponses;

    public function index(Request $request)
    {
        if ($user = $request->user('sanctum')) {
            Auth::setUser($user);
            App::setLocale($user->language);
        }

        return $this->responseSuccess([
            'data' => BackendEmbeds::toArray(),
        ]);
    }
}
