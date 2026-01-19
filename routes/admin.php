<?php

use App\Http\Controllers\V1\Admin\AdminUserController;
use App\Http\Controllers\V1\Admin\AuthController;
use App\Http\Controllers\V1\Admin\BrandController;
use App\Http\Controllers\V1\Admin\CategoryController;
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

    Route::apiResource('brands', BrandController::class);
    Route::prefix('brands')->group(function () {
        Route::patch('{id}/activate', [BrandController::class, 'activateBrand']);
        Route::patch('{id}/deactivate', [BrandController::class, 'deactivateBrand']);
        Route::delete('{id}/force', [BrandController::class, 'forceDestroy']);
        Route::patch('{id}/logo', [BrandController::class, 'updateLogo']);
        Route::delete('{id}/logo', [BrandController::class, 'removeLogo']);
        Route::post('{id}/restore', [BrandController::class, 'restore']);
        Route::patch('{id}/toggle-featured', [BrandController::class, 'toggleFeatured']);
    });

    Route::apiResource('categories', CategoryController::class);
    Route::prefix('categories')->group(function () {
        Route::patch('{id}/activate', [CategoryController::class, 'activate']);
        Route::patch('{id}/deactivate', [CategoryController::class, 'deactivate']);
        Route::patch('{id}/toggle-featured', [CategoryController::class, 'toggleFeatured']);
        Route::delete('{id}/force', [CategoryController::class, 'forceDestroy']);
        Route::post('{id}/restore', [CategoryController::class, 'restore']);
        Route::post('bulk-actions', [CategoryController::class, 'bulkActions']);
    });
});
