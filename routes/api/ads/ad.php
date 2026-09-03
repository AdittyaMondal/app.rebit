<?php


use Illuminate\Support\Facades\Route;

Route::get('/ad', [App\Http\Controllers\Api\Ad\AdController::class , 'getAd']);