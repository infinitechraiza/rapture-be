<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Support\Carbon;
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
            $events = Event::query()
                ->with('comedians')
                ->when($request->filled('search'), function ($query, $request) {
                    $query->where(function ($q) use ($request) {
                        $q->where('title', 'like', "%{$request->search}%")
                            ->orWhere('description', 'like', "%{$request->search}%");
                    });
                })
                ->orderBy('event_date')
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
        Log::info('Auth check on event store', [
            'auth_check' => auth()->check(),
            'auth_id' => auth()->id(),
            'has_cookie' => $request->hasHeader('Cookie'),
            'has_bearer' => $request->bearerToken(),
        ]);

        // if (!auth()->check()) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'You must be logged in to create an event.',
        //     ], 401);
        // }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'event_date' => 'required|date_format:Y-m-d',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'color' => 'required|string',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
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

            $imagePath = null;
            if ($request->hasFile('image')) {

                // Stores under storage/app/public/images/events
                $filename = time() . '_' . uniqid() . '.' . $request->file('image')->getClientOriginalExtension();

                $imagePath = $request->file('image')->storeAs(
                    'images/events',
                    $filename,
                    'public'
                );
            }

            $event = Event::create([
                'user_id' => auth()->id(),
                'title' => $validated['title'],
                'event_date' => $validated['event_date'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'color' => $validated['color'] ?? Event::DEFAULT_COLOR,
                'image' => $imagePath,
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
            'start_time' => 'sometimes|required',
            'end_time' => 'sometimes|required',
            'color' => 'sometimes|nullable|string|max:50',
            'description' => 'sometimes|nullable|string',
            'image' => 'sometimes|nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'comedians_id' => 'sometimes|required|array|min:1',
            'comedians_id.*' => 'integer|exists:comedians,id',
        ]);

        Log::info('Validation rules for event update', ['rules' => $validator->getRules()]);
        Log::info('Request data for event update', ['data' => $request->all()]);


        $validator->after(function ($validator) use ($request) {
            if ($request->input('start_time') === '00:00:00' || $request->input('start_time') === '24:00:00') {
                $request->merge(['start_time' => '24:00:00']);
            }

            if ($request->input('end_time') === '00:00:00' || $request->input('end_time') === '24:00:00') {
                $request->merge(['end_time' => '24:00:00']);
            }
        });

        $validator->validate();
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
                return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
            }

            $eventDate = \Carbon\Carbon::parse($event->event_date)->format('Y-m-d');
            $startTime = Carbon::createFromFormat('H:i:s', $request->start_time)
                ->format('H:i:s');

            $endTime = Carbon::createFromFormat('H:i:s', $request->end_time)
                ->format('H:i:s');

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

            if ($request->hasFile('image')) {
                if ($event->image && \Illuminate\Support\Facades\Storage::disk('public')->exists($event->image)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($event->image);
                }
                $event->image = $request->file('image')->store('images/events', 'public');
            }

            Log::info([
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
            ]);

            $event->fill($request->only([
                'title',
                'event_date',
                'start_time',
                'end_time',
                'color',
                'description',
            ]));


            $event->save();

            if ($request->has('comedians_id')) {
                $event->comedians()->sync($request->comedians_id);
            }

            return response()->json([
                'success' => true,
                'message' => 'Event updated successfully.',
                'data' => $event->fresh('comedians'),
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Event not found.'], 404);
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


            // Delete the associated image file, if one exists
            if ($event->image) {
                $imagePath = public_path('storage/' . $event->image);
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
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