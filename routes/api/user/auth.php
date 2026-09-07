<?php
/*
|--------------------------------------------------------------------------
| ColibriPlus - The Ultimate Social Network Web Application.
|--------------------------------------------------------------------------
| Author: Mansur Terla. Full-Stack Web Developer, UI/UX Designer.
| Website: www.terla.me
| E-mail: mansurtl.contact@gmail.com
| Instagram: @mansur_terla
| Telegram: @mansurtl_contact
|--------------------------------------------------------------------------
| Copyright (c)  ColibriPlus. All rights reserved.
|--------------------------------------------------------------------------
*/

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\PersonalAccessToken;

Route::post('/logout', function (Request $request) {
    // Native clients authenticate with a bearer token, so the session teardown
    // below is not enough on its own -- and a token request has no session at
    // all, because EnsureFrontendRequestsAreStateful only attaches one for
    // stateful origins. Session auth yields a TransientToken, which the
    // instanceof deliberately skips, so web behaviour is unchanged.
    $token = $request->user()?->currentAccessToken();

    if ($token instanceof PersonalAccessToken) {
        $token->delete();
    }

    auth()->guard('web')->logout();

    if ($request->hasSession()) {
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    return response()->json([
        'status' => 'success',
        'message' => 'Logged out successfully'
    ]);
});