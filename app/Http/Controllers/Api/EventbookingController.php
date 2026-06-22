<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventBooking;
use App\Models\EventBookingRoom;
use App\Models\UnavailableDate;
use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class EventBookingController extends Controller
{
    // ── POST /api/event-bookings ──────────────────────────────

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'venue_id'              => 'required|exists:venues,id',
            'check_in_date'         => 'required|date|after_or_equal:today',
            'check_out_date'        => 'required|date|after_or_equal:check_in_date',
            'is_single_day'         => 'required|boolean',
            'number_of_days'        => 'required|integer|min:1',
            'number_of_nights'      => 'required|integer|min:0',
            'expected_attendees'    => 'required|integer|min:1',
            'needs_accommodation'   => 'required|boolean',
            'organization'          => 'nullable|string|max:255',
            'event_name'            => 'nullable|string|max:255',
            'contact_person'        => 'required|string|max:255',
            'position'              => 'nullable|string|max:255',
            'email'                 => 'required|email|max:255',
            'phone'                 => 'required|string|max:50',
            'details'               => 'nullable|string',
            'venue_price_per_day'   => 'required|numeric|min:0',
            'venue_total'           => 'required|numeric|min:0',
            'rooms_total'           => 'nullable|numeric|min:0',
            'grand_total'           => 'required|numeric|min:0',
            'rooms'                             => 'nullable|array',
            'rooms.*.room_id'                   => 'required_with:rooms|string|max:10',
            'rooms.*.room_name'                 => 'required_with:rooms|string|max:255',
            'rooms.*.bed_type'                  => 'nullable|string|max:255',
            'rooms.*.capacity'                  => 'required_with:rooms|integer|min:1',
            'rooms.*.size'                      => 'nullable|string|max:255',
            'rooms.*.quantity'                  => 'required_with:rooms|integer|min:1',
            'rooms.*.price_per_night'           => 'required_with:rooms|numeric|min:0',
            'rooms.*.number_of_nights'          => 'required_with:rooms|integer|min:1',
            'rooms.*.subtotal'                  => 'required_with:rooms|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $venue = Venue::findOrFail($request->venue_id);

            // Check venue availability
            $unavailable = UnavailableDate::where('venue_id', $request->venue_id)
                ->whereBetween('unavailable_date', [$request->check_in_date, $request->check_out_date])
                ->exists();

            if ($unavailable) {
                return response()->json([
                    'success' => false,
                    'message' => 'Selected dates are not available for this venue.',
                ], 409);
            }

            // Check capacity
            if ($request->expected_attendees > $venue->max_guests) {
                return response()->json([
                    'success'             => false,
                    'message'             => 'Number of attendees exceeds venue capacity.',
                    'venue_capacity'      => $venue->max_guests,
                    'requested_attendees' => $request->expected_attendees,
                ], 422);
            }

            $booking = EventBooking::create([
                'user_id'             => auth()->id(),
                'venue_id'            => $request->venue_id,
                'check_in_date'       => $request->check_in_date,
                'check_out_date'      => $request->check_out_date,
                'is_single_day'       => $request->is_single_day,
                'number_of_days'      => $request->number_of_days,
                'number_of_nights'    => $request->number_of_nights,
                'expected_attendees'  => $request->expected_attendees,
                'needs_accommodation' => $request->needs_accommodation,
                'organization'        => $request->organization,
                'event_name'          => $request->event_name,
                'contact_person'      => $request->contact_person,
                'position'            => $request->position,
                'email'               => $request->email,
                'phone'               => $request->phone,
                'details'             => $request->details,
                'venue_price_per_day' => $request->venue_price_per_day,
                'venue_total'         => $request->venue_total,
                'rooms_total'         => $request->rooms_total ?? 0,
                'grand_total'         => $request->grand_total,
                'status'              => EventBooking::STATUS_PENDING,
            ]);

            if ($request->needs_accommodation && !empty($request->rooms)) {
                $roomRows = array_map(fn($room) => [
                    'event_booking_id' => $booking->id,
                    'room_id'          => $room['room_id'],
                    'room_name'        => $room['room_name'],
                    'bed_type'         => $room['bed_type'] ?? null,
                    'capacity'         => $room['capacity'],
                    'size'             => $room['size'] ?? null,
                    'quantity'         => $room['quantity'],
                    'price_per_night'  => $room['price_per_night'],
                    'number_of_nights' => $room['number_of_nights'],
                    'subtotal'         => $room['subtotal'],
                ], $request->rooms);

                EventBookingRoom::insert($roomRows);
            }

            DB::commit();

            $booking->load(['venue', 'rooms']);

            Log::info('New event booking created', [
                'booking_id' => $booking->id,
                'reference'  => $booking->reference_number,
                'venue'      => $venue->name,
                'contact'    => $request->contact_person,
                'email'      => $request->email,
                'is_guest'   => is_null(auth()->id()),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Event booking request submitted successfully! Our team will contact you within 24 hours.',
                'data'    => [
                    'booking_id'       => $booking->id,
                    'reference_number' => $booking->reference_number,
                    'status'           => $booking->status,
                    'venue_name'       => $venue->name,
                    'check_in_date'    => $booking->check_in_date,
                    'check_out_date'   => $booking->check_out_date,
                    'grand_total'      => $booking->grand_total,
                    'booking'          => $booking,
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Event booking store failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit booking request. Please try again.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // ── GET /api/event-bookings ───────────────────────────────

    public function index(Request $request)
    {
        try {
            $query = EventBooking::with(['venue', 'rooms', 'user']);

            // Non-admins only see their own bookings
            if (auth()->check() && !auth()->user()->is_admin) {
                $query->where('user_id', auth()->id());
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('email')) {
                $query->where('email', $request->email);
            }

            if ($request->filled('venue_id')) {
                $query->where('venue_id', $request->venue_id);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('contact_person', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('event_name', 'like', "%{$search}%")
                      ->orWhere('organization', 'like', "%{$search}%");
                });
            }

            $bookings = $query->orderBy('created_at', 'desc')
                              ->paginate($request->per_page ?? 15);

            return response()->json([
                'success' => true,
                'data'    => $bookings,
            ]);

        } catch (\Exception $e) {
            Log::error('Event booking index failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch bookings.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // ── GET /api/event-bookings/{id} ──────────────────────────

    public function show($id)
    {
        try {
            $booking = EventBooking::with(['venue', 'rooms', 'user'])->findOrFail($id);

            if (auth()->check() && !auth()->user()->is_admin) {
                if ($booking->user_id !== auth()->id()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized access.',
                    ], 403);
                }
            }

            return response()->json([
                'success' => true,
                'data'    => $booking,
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Booking not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch booking.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // ── PUT /api/event-bookings/{id} ──────────────────────────

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'event_name'          => 'sometimes|nullable|string|max:255',
            'organization'        => 'sometimes|nullable|string|max:255',
            'contact_person'      => 'sometimes|required|string|max:255',
            'position'            => 'sometimes|nullable|string|max:255',
            'email'               => 'sometimes|required|email|max:255',
            'phone'               => 'sometimes|required|string|max:50',
            'details'             => 'sometimes|nullable|string',
            'expected_attendees'  => 'sometimes|required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $booking = EventBooking::findOrFail($id);

            // Only allow edits on pending bookings unless admin
            if (!auth()->user()->is_admin && !$booking->isPending()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending bookings can be edited.',
                ], 403);
            }

            $booking->update($request->only([
                'event_name', 'organization', 'contact_person',
                'position', 'email', 'phone', 'details', 'expected_attendees',
            ]));

            $booking->load(['venue', 'rooms']);

            return response()->json([
                'success' => true,
                'message' => 'Booking updated successfully.',
                'data'    => $booking,
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Booking not found.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Event booking update failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update booking.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // ── PATCH /api/event-bookings/{id}/status ─────────────────

    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status'      => 'required|in:pending,confirmed,completed,rejected,cancelled',
            'admin_notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $booking = EventBooking::findOrFail($id);

            $timestamps = [
                'confirmed' => 'confirmed_at',
                'rejected'  => 'rejected_at',
                'completed' => 'completed_at',
            ];

            $update = ['status' => $request->status];

            if (isset($timestamps[$request->status])) {
                $update[$timestamps[$request->status]] = now();
            }

            if ($request->filled('admin_notes')) {
                $update['admin_notes'] = $request->admin_notes;
            }

            $booking->update($update);

            Log::info('Event booking status updated', [
                'booking_id' => $booking->id,
                'status'     => $request->status,
                'updated_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Booking status updated to {$request->status}.",
                'data'    => $booking->fresh(['venue', 'rooms']),
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Booking not found.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Event booking status update failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update booking status.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // ── DELETE /api/event-bookings/{id} ───────────────────────

    public function destroy($id)
    {
        try {
            $booking = EventBooking::findOrFail($id);

            if (!auth()->user()->is_admin && $booking->user_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized.',
                ], 403);
            }

            $booking->delete();

            return response()->json([
                'success' => true,
                'message' => 'Booking deleted successfully.',
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Booking not found.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Event booking delete failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete booking.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // ── GET /api/event-bookings/check-availability ────────────

    public function checkAvailability(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'venue_id'       => 'required|exists:venues,id',
            'check_in_date'  => 'required|date',
            'check_out_date' => 'required|date|after_or_equal:check_in_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $unavailableDates = UnavailableDate::where('venue_id', $request->venue_id)
                ->whereBetween('unavailable_date', [$request->check_in_date, $request->check_out_date])
                ->get();

            $isAvailable = $unavailableDates->isEmpty();

            return response()->json([
                'success'           => true,
                'is_available'      => $isAvailable,
                'unavailable_dates' => $unavailableDates->pluck('unavailable_date'),
                'message'           => $isAvailable ? 'Dates are available.' : 'Some dates are not available.',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check availability.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // ── GET /api/event-bookings/unavailable-dates ─────────────

    public function getUnavailableDates(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'venue_id'   => 'nullable|exists:venues,id',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $query = UnavailableDate::query();

            if ($request->filled('venue_id')) {
                $query->where(function ($q) use ($request) {
                    $q->where('venue_id', $request->venue_id)
                      ->orWhereNull('venue_id');
                });
            }

            if ($request->filled('start_date') && $request->filled('end_date')) {
                $query->whereBetween('unavailable_date', [$request->start_date, $request->end_date]);
            }

            return response()->json([
                'success' => true,
                'data'    => $query->orderBy('unavailable_date')->get(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch unavailable dates.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}