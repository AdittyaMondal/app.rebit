<?php


use Illuminate\Support\Facades\Route;

Route::get('report/reasons', [App\Http\Controllers\Api\User\Feedback\ReportController::class , 'getReportReasons']);
Route::post('report/send', [App\Http\Controllers\Api\User\Feedback\ReportController::class , 'sendReport']);