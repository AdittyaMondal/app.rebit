<?php

use Illuminate\Support\Facades\Route;

Route::get('/app', function () {
	$locale = (string) request()->get('locale', 'en');

	// Allow-list the locale before it can reach require(): this value is
	// user-controlled and previously built the include path directly.
	if (! preg_match('/^[a-z]{2}(_[A-Z]{2})?$/', $locale)) {
		return response()->json(['data' => []]);
	}

	$localePath = base_path("lang/{$locale}/api/index.php");

	if (! file_exists($localePath)) {
		return response()->json([
			'data' => []
		]);
	}

    return response()->json([
		'data' => require $localePath,
	]);
});
