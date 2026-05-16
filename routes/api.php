<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DayController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\SyncController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::get('/health', [AuthController::class, 'health']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/days', [DayController::class, 'index']);
    Route::get('/days/{date}', [DayController::class, 'show']);
    Route::put('/days/{date}', [DayController::class, 'upsert']);
    Route::delete('/days/{date}', [DayController::class, 'destroy']);

    Route::get('/settings', [SettingController::class, 'index']);
    Route::put('/settings', [SettingController::class, 'update']);

    Route::get('/export', [ExportController::class, 'export']);
    Route::post('/sync', [SyncController::class, 'sync']);
});
