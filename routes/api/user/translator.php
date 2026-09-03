<?php


use Illuminate\Support\Facades\Route;

Route::post('/translate', [App\Http\Controllers\Api\User\Translator\TranslatorController::class , 'translate']);