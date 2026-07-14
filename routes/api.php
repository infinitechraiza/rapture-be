<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AboutController;
use App\Http\Controllers\Api\GalleryController;
use App\Http\Controllers\Api\AboutValueController;
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

// Auth routes (private)
Route::middleware('auth:sanctum')->group(
    function () {
        // Auth routes (authenticated)
        Route::prefix('auth')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
        });
    },
);

// Events routes (public)
Route::prefix('users')->group(function () {
    Route::get('/', [UserController::class, 'index']);
    Route::post('/', [UserController::class, 'store']);
    Route::get('/{id}', [UserController::class, 'show']);
    Route::put('/{id}', [UserController::class, 'update']);
    Route::patch('/{id}/status', [UserController::class, 'updateStatus']);
    Route::get('/{id}/activity', [UserController::class, 'activity']);
});

// Make sure the event routes are inside this middleware group:
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/event', [EventController::class, 'store']);
    Route::match(['post', 'patch'], '/event/{id}', [EventController::class, 'update']);
    Route::delete('/event/{id}', [EventController::class, 'destroy']);
});

// keep public if desired
Route::get('/event', [EventController::class, 'index']);
Route::get('/event/{id}', [EventController::class, 'show']);

// Bookings routes (public)
Route::prefix('booking')->group(function () {
    Route::post('/', [BookingController::class, 'store']);
    Route::get('/{id}', [BookingController::class, 'show']);
});

// Bookings routes (private)
Route::prefix('booking')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [BookingController::class, 'index']);
    Route::post('/{id}', [BookingController::class, 'update']); // POST for form-data with images
    Route::delete('/{id}', [BookingController::class, 'destroy']);
    Route::patch('/{id}/status', [BookingController::class, 'updateStatus']);
    Route::delete('/booking/{id}', [BookingController::class, 'cancel']);
});


// Comedians routes (public)
Route::prefix('comedians')->group(function () {
    Route::get('/', [ComediansController::class, 'index']);
    Route::post('/', [ComediansController::class, 'store']);
    Route::get('/{id}', [ComediansController::class, 'show']);
    Route::match(['post', 'put'], '/{id}', [ComediansController::class, 'update']);
    Route::delete('/{id}', [ComediansController::class, 'destroy']);
});


// About routes (public)
Route::prefix('about')->group(function () {
    // Value cards must be registered before {id} catch-all to avoid
    // "values" being interpreted as a section id.
    Route::get('values', [AboutValueController::class, 'index']);
    Route::post('values', [AboutValueController::class, 'store']);
    Route::get('values/{id}', [AboutValueController::class, 'show']);
    Route::put('values/{id}', [AboutValueController::class, 'update']);
    Route::patch('values/{id}', [AboutValueController::class, 'update']);
    Route::delete('values/{id}', [AboutValueController::class, 'destroy']);

    Route::get('/', [AboutController::class, 'index']);
    Route::post('/', [AboutController::class, 'store']);
    Route::get('{id}', [AboutController::class, 'show'])->whereNumber('id');
    Route::put('{id}', [AboutController::class, 'update'])->whereNumber('id');
    Route::patch('{id}', [AboutController::class, 'update'])->whereNumber('id');
    Route::delete('{id}', [AboutController::class, 'destroy'])->whereNumber('id');
});



// Gallery routes (public)
Route::prefix('gallery')->group(function () {
    Route::get('/', [GalleryController::class, 'index']);
    Route::post('', [GalleryController::class, 'store']);
    Route::get('/{id}', [GalleryController::class, 'show']);
    Route::put('/{id}', [GalleryController::class, 'update']);
    Route::patch('/{id}', [GalleryController::class, 'update']);
    Route::delete('/{id}', [GalleryController::class, 'destroy']);
});



