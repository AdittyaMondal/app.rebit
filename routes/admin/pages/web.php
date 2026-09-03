<?php


use Illuminate\Support\Facades\Route;

Route::get('/', [App\Http\Controllers\Admin\Page\PageController::class , 'index'])->name('admin.pages.index');
Route::get('/{pageName}/edit', [App\Http\Controllers\Admin\Page\PageController::class , 'edit'])->name('admin.pages.edit');
Route::post('/store', [App\Http\Controllers\Admin\Page\PageController::class , 'store'])->name('admin.pages.store');