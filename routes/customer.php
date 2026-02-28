<?php

use App\Http\Controllers\V1\Customer\AuthController;
use App\Http\Controllers\V1\Customer\CartController;
use App\Http\Controllers\V1\Customer\CouponController;
use App\Http\Controllers\V1\Customer\DeliveryAddressController;
use App\Http\Controllers\V1\Customer\CheckoutController;
use App\Http\Controllers\V1\Customer\OrderController;
use App\Http\Controllers\V1\Customer\ProductReviewController;
use Illuminate\Support\Facades\Route;

// Public customer routes (no authentication required)
Route::prefix('v1/customer')->group(function () {

    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:auth');
    Route::get('verify-email', [AuthController::class, 'verifyEmail']);
    Route::post('resend-verification', [AuthController::class, 'resendVerification']);
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth');
    Route::get('auth/google', [AuthController::class, 'redirectToGoogle']);
    Route::get('auth/google/callback', [AuthController::class, 'handleGoogleCallback']);

    Route::post('auth/google/login', [AuthController::class, 'googleLogin']);

    Route::post('add', [CartController::class, 'addToCart']);
});

// Protected customer routes (authentication required)
Route::middleware(['auth:api', 'customer.auth'])->prefix('v1/customer')->group(function () {

    Route::get('me', [AuthController::class, 'me']);
    Route::post('profile-update', [AuthController::class, 'updateProfile']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('link', [AuthController::class, 'linkGoogleAccount']);
    Route::post('unlink', [AuthController::class, 'unlinkGoogleAccount']);

    Route::prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'getCart']);
        Route::post('add', [CartController::class, 'addToCart']);
        Route::put('update/{itemId}', [CartController::class, 'updateCartItem']);
        Route::delete('remove/{itemId}', [CartController::class, 'remove']);
        Route::delete('clear', [CartController::class, 'clearCart']);
        Route::post('merge', [CartController::class, 'mergeWithUserCart']);
    });

    Route::apiResource('delivery-addresses', DeliveryAddressController::class);

    Route::prefix('checkout')->group(function () {
        Route::post('/', [CheckoutController::class, 'store']);
        Route::get('{id}', [CheckoutController::class, 'show']);
        Route::post('{id}/apply-coupon', [CheckoutController::class, 'applyCoupon']);
        Route::delete('{id}/remove-coupon', [CheckoutController::class, 'removeCoupon']);
        Route::post('{id}/set-address', [CheckoutController::class, 'setDeliveryAddress']);
        Route::post('{id}/confirm', [OrderController::class, 'confirmCheckout']);
    });

    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::get('{id}', [OrderController::class, 'show']);
        Route::post('{id}/cancel', [OrderController::class, 'cancel']);
        Route::patch('{id}/received', [OrderController::class, 'markAsReceived']);
    });

    Route::prefix('reviews')->group(function () {
        Route::post('/', [ProductReviewController::class, 'store']);
        Route::get('product/{productId}', [ProductReviewController::class, 'getProductReviews']);
    });
});
