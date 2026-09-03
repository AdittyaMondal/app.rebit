<?php


use Illuminate\Support\Facades\Route;

Route::post('/bookmarks', [App\Http\Controllers\Api\User\Bookmark\BookmarkController::class , 'getBookmarks']);