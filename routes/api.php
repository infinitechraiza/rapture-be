<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\ComediansController;

use Illuminate\Support\Facades\Route;

// ============================================================================
// PUBLIC ROUTES (no authentication required)
// ============================================================================

// Auth routes
Route::prefix('auth')->group(function () {
    // Registration and Login
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    // Email and phone validation endpoints
    Route::post('/check-email', [AuthController::class, 'checkEmail']);
    Route::post('/check-phone', [AuthController::class, 'checkPhone']);

    // Forgot Password
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    // Email verification routes
    Route::get('/verify-email/{token}', [AuthController::class, 'verifyEmail']);
    Route::post('/resend-verification', [AuthController::class, 'resendVerification']);
});


Route::middleware('auth:sanctum')->group(
    function () {
        // Auth routes (authenticated)
        Route::prefix('auth')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
        });


    }
);


// Events routes (public)


// Make sure the event routes are inside this middleware group:
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('event')->group(function () {
        Route::get('/', [EventController::class, 'index']);
        Route::post('/', [EventController::class, 'store']);
        Route::get('/{id}', [EventController::class, 'show']);
        Route::match(['post', 'put'], '/{id}', [EventController::class, 'update']);
        Route::delete('/{id}', [EventController::class, 'destroy']);
    });



});


// Bookings routes (public)
Route::prefix('booking')->group(function () {
    Route::get('/', [BookingController::class, 'index']);
    Route::post('/', [BookingController::class, 'store']);
    Route::get('/{id}', [BookingController::class, 'show']);

    Route::post('/{id}', [BookingController::class, 'update']); // POST for form-data with images
    Route::delete('/{id}', [BookingController::class, 'destroy']);
    Route::patch('/{id}/status', [BookingController::class, 'updateStatus']);

});





// Comedians routes (public)
Route::prefix('comedians')->group(function () {
    Route::get('/', [ComediansController::class, 'index']);
    Route::post('/', [ComediansController::class, 'store']);
    Route::get('/{id}', [ComediansController::class, 'show']);
    Route::match(['post', 'put'], '/{id}', [ComediansController::class, 'update']);
    Route::delete('/{id}', [ComediansController::class, 'destroy']);
});