<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class EventController extends Controller
{
    // ── GET /api/event ─────────────────────────────────────────
    // Supports ?per_page=, ?year=&month= (calendar view), or ?start_date=&end_date=

    public function index(Request $request)
    {
        try {
            $query = Event::query();

            // Non-admins only see their own events
            if (auth()->check() && !auth()->user()->is_admin) {
                $query->where('user_id', auth()->id());
            }

            if ($request->filled('year') && $request->filled('month')) {
                $query->forMonth((int) $request->year, (int) $request->month);
            } elseif ($request->filled('start_date') && $request->filled('end_date')) {
                $query->betweenDates($request->start_date, $request->end_date);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }

            $events = $query->orderBy('event_date')
                ->orderBy('start_time')
                ->paginate($request->per_page ?? 15);

            return response()->json([
                'success' => true,
                'data' => $events,
            ]);

        } catch (\Exception $e) {
            Log::error('Event index failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch events.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // ── POST /api/event ────────────────────────────────────────


    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'exists:users,id',
            'title' => 'required|string|max:255',
            'event_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'color' => 'nullable|string|max:50',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = User::find($request->user_id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                ], 404);
            }


            $event = Event::create([
                'user_id' => $user->id,
                'title' => $request->title,
                'event_date' => $request->event_date,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'color' => $request->color ?? Event::DEFAULT_COLOR,
                'description' => $request->description,
            ]);

            Log::info('New event created', [
                'event_id' => $event->id,
                'title' => $event->title,
                'date' => $event->event_date,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Event created successfully.',
                'data' => $event,
            ], 201);

        } catch (\Exception $e) {
            Log::error('Event store failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create event. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // ── GET /api/event/{id} ────────────────────────────────────

    public function show($id)
    {
        try {
            $event = Event::findOrFail($id);

            if (auth()->check() && !auth()->user()->is_admin && $event->user_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.',
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => $event,
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Event not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch event.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // ── PUT/PATCH /api/event/{id} ──────────────────────────────

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'event_date' => 'sometimes|required|date',
            'start_time' => 'sometimes|required|date_format:H:i',
            'end_time' => 'sometimes|required|date_format:H:i|after:start_time',
            'color' => 'sometimes|nullable|string|max:50',
            'description' => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $event = Event::findOrFail($id);

            if (!auth()->user()->is_admin && $event->user_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized.',
                ], 403);
            }

            // end_time must be after start_time even when only one of the two is sent
            $startTime = $request->input('start_time', $event->start_time?->format('H:i'));
            $endTime = $request->input('end_time', $event->end_time?->format('H:i'));
            if ($startTime && $endTime && $endTime <= $startTime) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => ['end_time' => ['End time must be after start time.']],
                ], 422);
            }

            $event->update($request->only([
                'title',
                'event_date',
                'start_time',
                'end_time',
                'color',
                'description',
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Event updated successfully.',
                'data' => $event->fresh(),
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Event not found.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Event update failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update event.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // ── DELETE /api/event/{id} ─────────────────────────────────

    public function destroy($id)
    {
        try {
            $event = Event::findOrFail($id);

            if (!auth()->user()->is_admin && $event->user_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized.',
                ], 403);
            }

            $event->delete();

            return response()->json([
                'success' => true,
                'message' => 'Event deleted successfully.',
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Event not found.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Event delete failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete event.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}