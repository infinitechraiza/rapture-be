<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\VenueController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\EventBookingController;

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


Route::middleware('auth:sanctum')->group(function () {
    // Auth routes (authenticated)
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});




// Bookings routes (public)
Route::prefix('booking')->group(function () {
    Route::get('/', [BookingController::class, 'index']);
    Route::post('/', [BookingController::class, 'store']);
    Route::get('/{id}', [BookingController::class, 'show']);
    Route::post('/{id}', [BookingController::class, 'update']); // POST for form-data with images
    Route::delete('/{id}', [BookingController::class, 'destroy']);
});

// Venues routes (public)
Route::prefix('venues')->group(function () {
    Route::get('/', [VenueController::class, 'index']);
    Route::post('/', [VenueController::class, 'store']);
    Route::get('/{id}', [VenueController::class, 'show']);
    Route::match(['post', 'put'], '/{id}', [VenueController::class, 'update']);
    Route::delete('/{id}', [VenueController::class, 'destroy']);
});


// Users - public list
Route::get('/users', [UserController::class, 'index']);

// ============================================================================
// PROTECTED ROUTES (require authentication)
// ============================================================================

Route::middleware('auth:sanctum')->group(function () {

    // User profile routes
    Route::prefix('users')->group(function () {
        Route::put('/{id}/status', [UserController::class, 'updateStatus']);
        Route::get('/{id}', [UserController::class, 'show']);

        // Current user profile
        Route::get('/profile', [ProfileController::class, 'getProfile']);
        Route::put('/update-profile', [ProfileController::class, 'updateProfile']);
        Route::put('/update-contact', [ProfileController::class, 'updateContact']);
        Route::put('/change-password', [ProfileController::class, 'changePassword']);
    });

    // Bookings routes
    Route::prefix('bookings')->group(function () {
        Route::get('/', [BookingController::class, 'index']);
        Route::post('/', [BookingController::class, 'store']);
        Route::get('/{id}', [BookingController::class, 'show']);
        Route::patch('/{id}/status', [BookingController::class, 'updateStatus']);
        Route::post('/{id}/cancel', [BookingController::class, 'cancel']);
        Route::post('/check-availability', [BookingController::class, 'checkAvailability']);
    });
});

// ============================================================================
// ADMIN ROUTES (require admin authentication)
// ============================================================================

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin/event-bookings')->group(function () {
    // Event booking admin actions
    Route::post('/{id}/confirm', [EventBookingController::class, 'confirm']);
    Route::post('/{id}/reject', [EventBookingController::class, 'reject']);
    Route::post('/{id}/complete', [EventBookingController::class, 'complete']);
    Route::put('/{id}/notes', [EventBookingController::class, 'updateNotes']);
});