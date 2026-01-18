<?php

use App\Http\Controllers\V1\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\V1\Admin\BrandController;
use App\Http\Controllers\V1\Customer\AuthController as CustomerAuthController;
use App\Http\Controllers\V1\Customer\ProfileController as CustomerProfileController;
use App\Http\Controllers\V1\OrganizationController;
use App\Http\Controllers\V1\PermissionController;
use App\Http\Controllers\V1\RoleController;
use App\Http\Controllers\V1\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| ADMIN ROUTES
|--------------------------------------------------------------------------
*/

Route::prefix('v1/admin')->group(function () {

    // Public admin routes (no auth required)
    Route::post('login', [AdminAuthController::class, 'login']);

    // Protected admin routes
    Route::middleware(['auth:api', 'admin.auth'])->group(function () {
        Route::get('me', [AdminAuthController::class, 'me']);
        Route::post('logout', [AdminAuthController::class, 'logout']);

        // Permission & Role Management
        Route::apiResource('permissions', PermissionController::class);
        Route::get('roles/list', [RoleController::class, 'getAvailableRoles']);
        Route::apiResource('roles', RoleController::class);


        // Brand Management
        Route::apiResource('brands', BrandController::class);

        // Organization Management (if needed)
        // Route::apiResource('organizations', OrganizationController::class);

        // Future admin routes will go here:
        // Route::apiResource('products', ProductController::class);
        // Route::apiResource('orders', OrderController::class);
        // Route::apiResource('inventory', InventoryController::class);
        // etc...
    });
});

/*
|--------------------------------------------------------------------------
| CUSTOMER ROUTES
|--------------------------------------------------------------------------
*/

Route::prefix('v1/customer')->group(function () {

    // Public customer routes (no auth required)
    Route::post('register', [CustomerAuthController::class, 'register']);
    Route::post('login', [CustomerAuthController::class, 'login']);

    // Protected customer routes
    Route::middleware(['auth:api', 'customer.auth'])->group(function () {
        Route::get('me', [CustomerAuthController::class, 'me']);
        Route::post('logout', [CustomerAuthController::class, 'logout']);

        // Profile Management
        Route::get('profile', [CustomerProfileController::class, 'show']);
        Route::put('profile', [CustomerProfileController::class, 'update']);

        // Future customer routes will go here:
        // Route::get('products', [ProductController::class, 'index']);
        // Route::get('products/{id}', [ProductController::class, 'show']);
        // Route::apiResource('cart', CartController::class);
        // Route::apiResource('orders', OrderController::class);
        // Route::apiResource('repair-requests', RepairRequestController::class);
        // etc...
    });
});

