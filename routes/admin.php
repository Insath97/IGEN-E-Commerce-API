<?php

use App\Http\Controllers\V1\Admin\AdminUserController;
use App\Http\Controllers\V1\Admin\AuthController;
use App\Http\Controllers\V1\PermissionController;
use App\Http\Controllers\V1\RoleController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/admin')->group(function () {

    Route::post('login', [AuthController::class, 'login']);
});

// Protected admin routes
Route::middleware(['auth:api', 'admin.auth'])->prefix('v1/admin')->group(function () {

    Route::get('me', [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);

    Route::apiResource('permissions', PermissionController::class);

    Route::get('roles/list/', [RoleController::class, 'getAvailableRoles']);
    Route::apiResource('roles', RoleController::class);

    Route::apiResource('users', AdminUserController::class);
    Route::prefix('users')->group(function () {
        Route::patch('{id}/activate', [AdminUserController::class, 'activate']);
        Route::patch('{id}/deactivate', [AdminUserController::class, 'deactivate']);
        Route::patch('{id}/profile-image', [AdminUserController::class, 'updateProfileImage']);
        Route::delete('{id}/profile-image', [AdminUserController::class, 'removeProfileImage']);
    });
});
