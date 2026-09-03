<?php


use Illuminate\Support\Facades\Route;

Route::get('/profile', [App\Http\Controllers\Api\User\Profile\ProfileController::class , 'getProfileData']);
Route::get('/profile/posts', [App\Http\Controllers\Api\User\Profile\ProfileController::class , 'getProfilePosts']);
Route::get('/profile/details', [App\Http\Controllers\Api\User\Profile\ProfileController::class , 'getProfileDetails']);
Route::get('/profile/followers', [App\Http\Controllers\Api\User\Profile\ProfileController::class , 'getProfileFollowers']);
Route::get('/profile/followings', [App\Http\Controllers\Api\User\Profile\ProfileController::class , 'getProfileFollowings']);