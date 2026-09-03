<?php


use Illuminate\Support\Facades\Route;

Route::get('/follow', [App\Http\Controllers\Api\User\Recommend\FollowRecommendController::class , 'getFollowRecommendations']);