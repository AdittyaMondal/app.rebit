<?php


use Illuminate\Support\Facades\Route;

Route::get('/bootstrap', [App\Http\Controllers\Api\User\Bootstrap\BootstrapController::class , 'bootstrap']);