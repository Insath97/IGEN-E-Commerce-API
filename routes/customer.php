<?php

use App\Http\Controllers\V1\Customer\AuthController;
use Illuminate\Support\Facades\Route;

// Public customer routes (no authentication required)
Route::prefix('v1/customer')->group(function () {

    Route::post('register', [AuthController::class, 'register']);

    Route::get('verify-email', [AuthController::class, 'verifyEmail']);
    Route::post('resend-verification', [AuthController::class, 'resendVerification']);

    Route::post('login', [AuthController::class, 'login']);
});

// Protected customer routes (authentication required)
Route::middleware(['auth:api', 'customer.auth'])->prefix('v1/customer')->group(function () {

    Route::get('me', [AuthController::class, 'me']);

    Route::post('logout', [AuthController::class, 'logout']);
});
