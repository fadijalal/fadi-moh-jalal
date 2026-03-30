<?php

use App\Http\Controllers\Api\UcasApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/ucas/login-and-sync', [UcasApiController::class, 'loginAndSync']);
    Route::get('/ucas/students/{student}', [UcasApiController::class, 'showStudentTable']);
});