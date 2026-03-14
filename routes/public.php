<?php

use App\Http\Controllers\V1\Frondend\CMSController;
use App\Http\Controllers\V1\Frondend\PublicController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/public')->group(function () {
    // Public routes can be defined here
    Route::get('featured-brands', [PublicController::class, 'featuredBrands']);

    Route::get('categories', [PublicController::class, 'getCategories']);

    Route::get('products', [PublicController::class, 'productsGetAll']);
    Route::get('trending-products', [PublicController::class, 'trendingProducts']);
    Route::get('featured-products', [PublicController::class, 'featuredProducts']);
    Route::get('products/{id}', [PublicController::class, 'ProductById']);

    Route::post('contact', [PublicController::class, 'sendContactMail']);

    Route::get('cms/{page}', [CMSController::class, 'getPageContent']);
});
