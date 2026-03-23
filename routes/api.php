<?php

use Illuminate\Support\Facades\Route;

// Health Check endpoint
Route::get('/health-check', function () {
    return response()->json(['message' => 'IGEN E-commerce web application API is working!']);
});

/* public routes */
require __DIR__ . '/public.php';

/* admin routes */
require __DIR__ . '/admin.php';

/* customer routes */
require __DIR__ . '/customer.php';

// Test route for sanitization verification (Remove in production if needed)
Route::post('/test-sanitization', function (\Illuminate\Http\Request $request) {
    return response()->json($request->all());
});


