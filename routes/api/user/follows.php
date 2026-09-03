<?php


use Illuminate\Support\Facades\Route;

Route::post('/follow/user', [App\Http\Controllers\Api\User\Follows\FollowsController::class , 'followUser']);
Route::post('/accept/user', [App\Http\Controllers\Api\User\Follows\FollowsController::class , 'acceptFollowRequest']);