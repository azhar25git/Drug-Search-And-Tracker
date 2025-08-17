<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DrugController;
use Illuminate\Support\Facades\Route;

// prefix: /api/v1

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/search', [DrugController::class, 'search'])->middleware('throttle:10,1');  // Rate limit: 10 requests/min

Route::middleware('auth:sanctum')->group(function () {
    // auth routes goes here
    Route::post('/drugs', [DrugController::class, 'add']);
    Route::delete('/drugs', [DrugController::class, 'delete']);
    Route::get('/drugs', [DrugController::class, 'list']);
});
