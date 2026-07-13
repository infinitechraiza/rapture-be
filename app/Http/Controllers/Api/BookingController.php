<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class BookingController extends Controller
{
    /**
     * List bookings for the authenticated user only.
     * Falls back to matching by email if user_id isn't set on older rows.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Booking::with('events.comedians')->orderBy('scheduled_at', 'desc');

        if ($user) {
            $query->where(function ($q) use ($user) {
                $q->where('user_id', auth()->id());
                if ($user->email) {
                    $q->orWhere('email', $user->email);
                }
            });
        } else {
            // No authenticated user — don't leak everyone's bookings.
            return response()->json([
                'success' => false,
                'message' => 'Please log in to view your reservations.',
            ], 401);
        }

        $bookings = $query->get();

        return response()->json([
            'success' => true,
            'data' => $bookings,
        ]);
    }

    /**
     * Show a single booking.
     */
    public function show($id)
    {
        $booking = Booking::with('events.comedians')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $booking,
        ]);
    }

    /**
     * Create a new appointment booking.
     * Expects: name, email, phone, booking_date (e.g. "2025-07-01 09:00:00")
     */
    public function store(Request $request)
    {
       $scheduledAt = Carbon::parse($request->booking_date);

        $user = User::where('email', $request->email)->first();
        $booking = Booking::create([
            'full_name' => $request->name,
            // FIX: previously never set, so bookings had no owner and
            // couldn't be scoped back to a user in index() above.
            'user_id' => $user['id'] ?? null,
            'email' => $request->email,
            'phone' => $request->phone,
            'date' => $scheduledAt->copy()->startOfDay(),
            'scheduled_at' => $scheduledAt,
            'status' => 'pending',
            'notes' => $request->notes,
        ]);
         $eventIds = $request->input('event_ids', []);

        if (is_array($eventIds) && !empty($eventIds)) {
            $alreadyBooked = Booking::forEvents($eventIds)
                ->with('events:id,title')
                ->get()
                ->flatMap(fn($booking) => $booking->events)
                ->unique('id');

            if ($alreadyBooked->isNotEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Selected events are already booked: '
                        . $alreadyBooked->pluck('title')->implode(', '),
                    'errors' => [
                        'event_ids' => [
                            'These events already have an active booking: '
                            . $alreadyBooked->pluck('title')->implode(', ')
                        ],
                    ],
                ], 422);
            }
        }

        $scheduledAt = Carbon::parse($request->booking_date);

        $user = User::where('email', $request->email)->first();
        $booking = Booking::create([
            'full_name' => $request->name,
            'user_id' => $user['id'] ?? null,
            'email' => $request->email,
            'phone' => $request->phone,
            'date' => $scheduledAt->copy()->startOfDay(),
            'scheduled_at' => $scheduledAt,
            'status' => 'pending',
            'notes' => $request->notes,
        ]);

        if (is_array($eventIds) && !empty($eventIds)) {
            $booking->events()->attach($eventIds);
        }

        $booking->load('events.comedians');

        return response()->json([
            'success' => true,
            'message' => 'Booking created successfully',
            'data' => $booking,
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
                'errors' => $validator->errors(),
            ], 422);
        }

        $booking = Booking::findOrFail($id);

        if ($request->status === 'cancelled' && !$booking->can_cancel) {
            return response()->json([
                'success' => false,
                'message' => 'Cancellation is only allowed more than 24 hours before the appointment.',
            ], 400);
        }

        $booking->update(['status' => $request->status]);
        $booking->load('events.comedians');

        return response()->json([
            'success' => true,
            'message' => 'Booking status updated successfully',
            'data' => $booking,
        ]);
    }

    public function cancel($id)
    {
        $booking = Booking::findOrFail($id);

        if (!$booking->can_cancel) {
            return response()->json([
                'success' => false,
                'message' => 'Cancellation is only allowed more than 24 hours before the appointment.',
            ], 400);
        }

        $booking->update(['status' => 'cancelled']);

        return response()->json([
            'success' => true,
            'message' => 'Booking cancelled successfully',
            'data' => $booking,
        ]);
    }

    public function checkAvailability(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'booking_date' => 'required|date|after:now',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $scheduledAt = Carbon::parse($request->booking_date);

        $isAvailable = !Booking::where('scheduled_at', $scheduledAt)
            ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
            ->exists();

        return response()->json([
            'success' => true,
            'data' => [
                'available' => $isAvailable,
                'scheduled_at' => $scheduledAt->toDateTimeString(),
            ],
        ]);
    }
}