<?php


use Illuminate\Support\Facades\Route;

Route::get('/', [App\Http\Controllers\Admin\Authorship\AuthorshipController::class , 'index'])->name('admin.authorship.index');