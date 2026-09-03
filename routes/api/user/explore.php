<?php


use Illuminate\Support\Facades\Route;

Route::post('/people', [App\Http\Controllers\Api\User\Explore\ExploreController::class , 'getPeople']);
Route::post('/posts', [App\Http\Controllers\Api\User\Explore\ExploreController::class , 'getPosts']);