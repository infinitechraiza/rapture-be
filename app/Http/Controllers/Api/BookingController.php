<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class BookingController extends Controller
{
    /**
     * List all bookings (no user_id — table has no auth relation).
     */
    public function index()
    {
        $bookings = Booking::orderBy('scheduled_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data'    => $bookings,
        ]);
    }

    /**
     * Show a single booking.
     */
    public function show($id)
    {
        $booking = Booking::findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $booking,
        ]);
    }

    /**
     * Create a new appointment booking.
     * Expects: name, email, phone, booking_date (e.g. "2025-07-01 09:00:00")
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'         => 'string|max:255',
            'email'        => 'nullable|email|max:255',
            'phone'        => 'nullable|string|max:50',
            'booking_date' => 'date|after:now',
            'notes'        => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $scheduledAt = Carbon::parse($request->booking_date);

        // Prevent double-booking the same time slot
        $conflict = Booking::where('scheduled_at', $scheduledAt)
            ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
            ->exists();

        if ($conflict) {
            return response()->json([
                'success' => false,
                'message' => 'This time slot is already booked. Please choose another.',
            ], 409);
        }

        $booking = Booking::create([
            'full_name'    => $request->name,
            'email'        => $request->email,
            'phone'        => $request->phone,
            'date'         => $scheduledAt->startOfDay(),
            'scheduled_at' => $scheduledAt,
            'status'       => 'pending',
            'notes'        => $request->notes,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Booking created successfully',
            'data'    => $booking,
        ], 201);
    }

    /**
     * Update booking status (confirmed, cancelled, in_progress, completed).
     */
    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,confirmed,in_progress,completed,cancelled',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $booking = Booking::findOrFail($id);

        if ($request->status === 'cancelled' && ! $booking->can_cancel) {
            return response()->json([
                'success' => false,
                'message' => 'Cancellation is only allowed more than 24 hours before the appointment.',
            ], 400);
        }

        $booking->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'message' => 'Booking status updated successfully',
            'data'    => $booking,
        ]);
    }

    /**
     * Cancel a booking.
     */
    public function cancel($id)
    {
        $booking = Booking::findOrFail($id);

        if (! $booking->can_cancel) {
            return response()->json([
                'success' => false,
                'message' => 'Cancellation is only allowed more than 24 hours before the appointment.',
            ], 400);
        }

        $booking->update(['status' => 'cancelled']);

        return response()->json([
            'success' => true,
            'message' => 'Booking cancelled successfully',
            'data'    => $booking,
        ]);
    }

    /**
     * Check whether a specific datetime slot is available.
     */
    public function checkAvailability(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'booking_date' => 'required|date|after:now',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $scheduledAt = Carbon::parse($request->booking_date);

        $isAvailable = ! Booking::where('scheduled_at', $scheduledAt)
            ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
            ->exists();

        return response()->json([
            'success' => true,
            'data'    => [
                'available'    => $isAvailable,
                'scheduled_at' => $scheduledAt->toDateTimeString(),
            ],
        ]);
    }
}