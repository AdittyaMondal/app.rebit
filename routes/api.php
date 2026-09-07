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
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

// Exchanges the one-time handoff code for a bearer token. Unauthenticated by
// design: the code plus the PKCE verifier are the credential.
Route::post('/auth/native/exchange', [App\Http\Controllers\User\Auth\NativeAuthController::class, 'exchange'])
    ->middleware('throttle:10,1');

// Additive twin of the framework's /broadcasting/auth, which lives in the web
// group and therefore only understands session + CSRF. Native clients hold a
// bearer token instead. Changing the original route's middleware would alter
// web behaviour; this leaves it untouched.
Route::post('/broadcasting/auth', fn (Request $request) => Broadcast::auth($request))
    ->middleware(['auth:sanctum', 'throttle:120,1']);

Route::prefix('translations')->middleware(['throttle:60,1'])->group(base_path('routes/api/translations.php'));

Route::prefix('bootstrap')->middleware(['auth:sanctum', 'throttle:60,1'])->group(base_path('routes/api/user/bootstrap.php'));

Route::prefix('settings')->middleware(['auth:sanctum', 'throttle:60,1'])->group(base_path('routes/api/user/account_settings.php'));

Route::prefix('auth')->middleware(['auth:sanctum', 'throttle:60,1'])->group(base_path('routes/api/user/auth.php'));

Route::prefix('post/editor')->middleware(['auth:sanctum', 'throttle:240,1'])->group(base_path('routes/api/user/post_editor.php'));

Route::prefix('story/editor')->middleware(['auth:sanctum', 'throttle:60,1'])->group(base_path('routes/api/user/story_editor.php'));

Route::prefix('timeline')->middleware(['auth:sanctum', 'throttle:240,1'])->group(base_path('routes/api/user/timeline.php'));

Route::prefix('stories')->middleware(['auth:sanctum', 'throttle:60,1'])->group(base_path('routes/api/user/stories.php'));

Route::prefix('profile')->middleware(['auth:sanctum', 'throttle:60,1'])->group(base_path('routes/api/user/profile.php'));

Route::prefix('relations')->middleware(['auth:sanctum', 'throttle:60,1'])->group(base_path('routes/api/user/relations.php'));

Route::prefix('marketplace')->middleware(['auth:sanctum', 'throttle:60,1'])->group(base_path('routes/api/user/marketplace.php'));

Route::prefix('jobs')->middleware(['auth:sanctum', 'throttle:60,1'])->group(base_path('routes/api/user/jobs.php'));

Route::prefix('messenger')->middleware(['auth:sanctum', 'throttle:60,1'])->group(base_path('routes/api/user/messenger.php'));

Route::prefix('admin')->middleware(['auth:sanctum', 'throttle:60,1'])->group(base_path('routes/api/user/admin.php'));

Route::prefix('recommendations')->middleware(['auth:sanctum', 'throttle:60,1'])->group(base_path('routes/api/user/recommend.php'));

Route::prefix('explore')->middleware(['auth:sanctum', 'throttle:60,1'])->group(base_path('routes/api/user/explore.php'));

Route::prefix('notifications')->middleware(['auth:sanctum', 'throttle:60,1'])->group(base_path('routes/api/user/notifications.php'));

Route::prefix('autocompletes')->middleware(['auth:sanctum', 'throttle:60,1'])->group(base_path('routes/api/user/autocompletes.php'));

Route::prefix('translator')->middleware(['auth:sanctum', 'throttle:60,1'])->group(base_path('routes/api/user/translator.php'));

Route::prefix('feedback')->middleware(['auth:sanctum', 'throttle:60,1'])->group(base_path('routes/api/user/feedback.php'));

Route::prefix('bookmarks')->middleware(['auth:sanctum', 'throttle:60,1'])->group(base_path('routes/api/user/bookmarks.php'));

// features.status:wallet makes the whole wallet API 404 when the flag is off.
// NativeClientMiddleware turns it off for the app, so /api/wallet/deposit --
// which returns a hosted Stripe/PayPal checkout URL -- is unreachable there.
// This also closes a gap on the web, where switching the wallet off in
// WalletSettings previously left the API wide open.
Route::prefix('wallet')->middleware(['auth:sanctum', 'features.status:wallet', 'throttle:60,1'])->group(base_path('routes/api/user/wallet.php'));

Route::prefix('system')->middleware(['throttle:60,1'])->group(base_path('routes/api/system/master.php'));

Route::prefix('ads')->middleware(['throttle:60,1'])->group(base_path('routes/api/ads/ad.php'));

Route::prefix('tips')->middleware(['auth:sanctum', 'throttle:60,1'])->group(base_path('routes/api/user/tips.php'));

Route::prefix('pins')->middleware(['auth:sanctum', 'throttle:60,1'])->group(base_path('routes/api/user/pins.php'));

Route::prefix('ai')->middleware(['auth:sanctum', 'throttle:60,1'])->group(base_path('routes/api/ai/user.php'));
