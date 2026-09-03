<?php


use Illuminate\Support\Facades\Route;

Route::get('/', [App\Http\Controllers\Admin\Post\PostController::class , 'index'])->name('admin.posts.index');

Route::middleware('sided.layout')->get('/show/{postId}', [App\Http\Controllers\Admin\Post\PostController::class , 'show'])->name('admin.posts.show');

Route::middleware('sided.layout')->post('/delete/{postId}', [App\Http\Controllers\Admin\Post\PostController::class , 'destroy'])->name('admin.posts.destroy');