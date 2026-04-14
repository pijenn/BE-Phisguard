<?php

use App\Http\Controllers\ReportController;
use App\Http\Controllers\AdminAuthController;


Route::post('/report', [ReportController::class, 'store']);
Route::post('/admin/login', [AdminAuthController::class, 'login']);

Route::get('/admin/report', [ReportController::class, 'index']);
Route::post('/admin/report/{id}/action', [ReportController::class, 'updateStatus']);
Route::get('/admin/report/{id}', [ReportController::class, 'show']);

Route::prefix('admin/dashboard')->group(function () {
    Route::get('/weekly-trend', [ReportController::class, 'weeklyTrend']);
    Route::get('/top-channel', [ReportController::class, 'topChannel']);
    Route::get('/top-modus', [ReportController::class, 'topModus']);
    Route::get('/segmentation', [ReportController::class, 'segmentation']);
});