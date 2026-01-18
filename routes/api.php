<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// Health Check endpoint
Route::get('/health-check', function () {
    return response()->json(['message' => 'IGEN E-commerce web application API is working!']);
});

/*  */
require __DIR__ . '/admin.php';
/* require __DIR__ . '/v1.php'; */
