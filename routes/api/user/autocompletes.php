<?php


use Illuminate\Support\Facades\Route;

Route::post('/mentions', [App\Http\Controllers\Api\User\Search\AutocompleteController::class , 'searchMentions']);