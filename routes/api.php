<?php

use App\Http\Controllers\ReportController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\ResponsController;


Route::post('/report', [ReportController::class, 'store']);

Route::get('/report/{id}', [ReportController::class, 'show']);
Route::get('/report', [ReportController::class, 'index']);

Route::post('/admin/login', [AdminAuthController::class, 'login']);
Route::post('/admin/report/{id}/action', [ReportController::class, 'updateStatus']);


Route::prefix('admin/dashboard')->group(function () {
    Route::get('/weekly-trend', [ReportController::class, 'weeklyTrend']);
    Route::get('/top-channel', [ReportController::class, 'topChannel']);
    Route::get('/top-modus', [ReportController::class, 'topModus']);
    Route::get('/segmentation', [ReportController::class, 'segmentation']);
});

Route::prefix('articles')->group(function () {
    Route::get('/', [ArticleController::class, 'index']);
    Route::get('/{id}', [ArticleController::class, 'show']);
    Route::post('/', [ArticleController::class, 'store']);
    Route::patch('/{id}', [ArticleController::class, 'update']);
    Route::delete('/{id}', [ArticleController::class, 'destroy']);
});

Route::prefix('respons')->group(function () {
    Route::get('/', [ResponsController::class, 'index']);
    Route::get('/{id}', [ResponsController::class, 'show']);
});

Route::prefix('admin/respons')->group(function () {
    Route::post('/', [ResponsController::class, 'store']);
    Route::patch('/{id}', [ResponsController::class, 'update']);
    Route::delete('/{id}', [ResponsController::class, 'destroy']);
});