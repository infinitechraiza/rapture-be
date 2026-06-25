<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Comedian;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class EventController extends Controller
{
    /**
     * GET /api/event
     * Supports ?per_page=, ?year=&month= (calendar view), or ?start_date=&end_date=
     */
    public function index(Request $request)
    {
        try {
            $query = Event::query()->with('comedians');

            // Non-admins only see their own events
            if (auth()->check() && !auth()->user()->is_admin) {
                $query->where(function ($q) {
                    $q->where('user_id', auth()->id())
                        ->orWhereNull('user_id'); // ← also show events with no user_id
                });
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

    /**
     * POST /api/event
     * Create a new event with comedians
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'event_date' => 'required|date_format:Y-m-d',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'color' => 'required|string',
            'description' => 'nullable|string',
            'comedian_ids' => 'required|array|min:1',
            'comedian_ids.*' => 'integer|exists:comedians,id',
        ]);

       

        try {
            $overlapping = Event::overlapping(
                $validated['event_date'],
                $validated['start_time'],
                $validated['end_time']
            )->first();

            if ($overlapping) {
                return response()->json([
                    'success' => false,
                    'message' => "We don't insert the event because it overlaps with an existing event "
                        . "(\"{$overlapping->title}\" from {$overlapping->start_time} to {$overlapping->end_time}) on {$validated['event_date']}.",
                    'errors' => [
                        'start_time' => ['The time in your event is already taken by an existing event.'],
                        'end_time' => ['Please choose a different time.'],
                    ],
                ], 422);
            }

            $event = Event::create([
                'user_id' => $request->user_id ?? auth()->id(),
                'title' => $validated['title'],
                'event_date' => $validated['event_date'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'color' => $validated['color'] ?? Event::DEFAULT_COLOR,
                'description' => $validated['description'] ?? null,
            ]);

            $event->comedians()->attach($validated['comedian_ids']);
            $event->load('comedians');

            return response()->json([
                'success' => true,
                'message' => 'Event created successfully',
                'data' => $event,
            ], 201);

        } catch (\Exception $e) {
            Log::error('Event creation failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create event',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * GET /api/event/{id}
     */
    public function show($id)
    {
        try {
            $event = Event::with('comedians')->findOrFail($id);

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
            Log::error('Event show failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch event.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * PUT/PATCH /api/event/{id}
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'event_date' => 'sometimes|required|date_format:Y-m-d',
            'start_time' => 'sometimes|required|date_format:H:i',
            'end_time' => 'sometimes|required|date_format:H:i',
            'color' => 'sometimes|nullable|string|max:50',
            'description' => 'sometimes|nullable|string',
            'comedians_id' => 'sometimes|required|array|min:1',
            'comedians_id.*' => 'integer|exists:comedians,id',
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

            if (auth()->check() && !auth()->user()->is_admin && $event->user_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized.',
                ], 403);
            }

            // Resolve the effective date/start/end (use new value if given, else existing)
            $eventDate = $request->input('event_date', $event->event_date);
            $startTime = $request->input('start_time', $event->start_time);
            $endTime = $request->input('end_time', $event->end_time);

           
            // ── Overlap check (excluding this event itself) ─────────
            $overlapping = Event::overlapping($eventDate, $startTime, $endTime, $event->id)->first();

            if ($overlapping) {
                return response()->json([
                    'success' => false,
                    'message' => "We don't update the event because it overlaps with an existing event "
                        . "(\"{$overlapping->title}\" from {$overlapping->start_time} to {$overlapping->end_time}) on {$eventDate}.",
                    'errors' => [
                        'start_time' => ['This time range overlaps with an existing event.'],
                        'end_time' => ['This time range overlaps with an existing event.'],
                    ],
                ], 422);
            }

            // Update event details
            $event->update($request->only([
                'title',
                'event_date',
                'start_time',
                'end_time',
                'color',
                'description',
            ]));

            // Sync comedians if provided
            if ($request->has('comedians_id')) {
                $event->comedians()->sync($request->comedians_id);
            }

            return response()->json([
                'success' => true,
                'message' => 'Event updated successfully.',
                'data' => $event->fresh('comedians'),
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

    /**
     * DELETE /api/event/{id}
     */
    public function destroy($id)
    {
        try {
            $event = Event::findOrFail($id);

            if (auth()->check() && !auth()->user()->is_admin && $event->user_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized.',
                ], 403);
            }

            // Detach comedians before deleting (or use cascade in migration)
            $event->comedians()->detach();

            // Delete event (or use SoftDelete)
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