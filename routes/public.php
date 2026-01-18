<?php

use App\Http\Controllers\V1\Frondend\PublicController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/public')->group(function () {
    // Public routes can be defined here
    Route::get('featured-brands', [PublicController::class, 'featuredBrands']);
});
