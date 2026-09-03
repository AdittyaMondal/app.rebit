<?php


use Illuminate\Support\Facades\Route;

Route::name('downloads.')->prefix('file-downloads')->group(function () {
    Route::get('/post/download-document/{mediaId}/file', [App\Http\Controllers\Downloads\Documents\DocumentDownloadController::class , 'downloadDocument'])->name('document.index');
});