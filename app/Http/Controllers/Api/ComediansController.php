<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comedian;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ComediansController extends Controller
{
    /**
     * Display a listing of comedians
     */
    public function index(Request $request)
    {
        try {
            Log::info('Comedians API called', $request->all());

            $query = Comedian::query();

            // Filter by status
            $status = $request->get('status', 'all');
            if ($status !== 'all' && in_array($status, ['active', 'inactive'])) {
                $query->where('status', $status);
            }

            // Search
            if ($request->has('search') && !empty($request->search)) {
                $query->search($request->search);
            }

            // Sorting
            $sortBy = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');

            $allowedSortFields = ['name', 'tagline', 'genre', 'created_at', 'id'];
            $sortBy = in_array($sortBy, $allowedSortFields) ? $sortBy : 'created_at';
            $sortOrder = in_array(strtolower($sortOrder), ['asc', 'desc']) ? strtolower($sortOrder) : 'desc';

            $query->orderBy($sortBy, $sortOrder);

            // Get total
            $total = $query->count();

            // Pagination
            $perPage = $request->get('per_page', 10);
            $page = $request->get('page', 1);

            $comedians = $query->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();

            $from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
            $to = min($page * $perPage, $total);

            $result = [
                'success' => true,
                'data' => [
                    'data' => $comedians,
                    'total' => $total,
                    'per_page' => (int) $perPage,
                    'current_page' => (int) $page,
                    'last_page' => (int) ceil($total / $perPage),
                    'from' => $from,
                    'to' => $to,
                ],
            ];

            Log::info('Comedians API response', ['count' => $comedians->count()]);

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Error fetching comedians', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch comedians',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Display a single comedian
     */
    public function show($id)
    {
        try {
            $comedian = Comedian::find($id);

            if (!$comedian) {
                return response()->json([
                    'success' => false,
                    'message' => 'Comedian not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $comedian
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching comedian', [
                'comedian_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch comedian',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Store a newly created comedian
     */
    public function store(Request $request)
    {


        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'tagline' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'genre' => 'nullable|string|max:100',
            'bio' => 'nullable|string',
            'status' => 'nullable|in:active,inactive',
        ]);


        try {
            $imagePath = null;
            if ($request->hasFile('image')) {
                // Stores under storage/app/public/images/events

                $filename = time() . '_' . uniqid() . '.' . $request->file('image')->getClientOriginalExtension();

                $imagePath = $request->file('image')->storeAs(
                    'images/comedians',
                    $filename,
                    'public'
                );
            }

            $event = Comedian::create([
                'name' => $validated['name'],
                'tagline' => $validated['tagline'] ?? null,
                'image' => $imagePath,
                'genre' => $validated['genre'] ?? null,
                'bio' => $validated['bio'] ?? null,
                'status' => $validated['status'] ?? 'active',
            ]);

            $item = Comedian::find($event->id);
            return response()->json([
                'success' => true,
                'message' => 'Comedian created successfully',
                'data' => $item,
            ], 201);

        } catch (\Exception $e) {
            Log::error('Comedian creation failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create comedian',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Update a comedian
     */
    public function update(Request $request, $id)
    {


        Log::info('=== Comedian update() called ===', [
            'comedian_id' => $id,
            'has_file_image' => $request->hasFile('image'),
            'content_type' => $request->header('Content-Type'),
            'method' => $request->method(),
            'all_input_keys' => array_keys($request->all()),
            'raw_body_keys' => array_keys($request->request->all()),
        ]);

        $comedian = Comedian::find($id);

        if (!$comedian) {
            return response()->json([
                'success' => false,
                'message' => 'Comedian not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'tagline' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'genre' => 'nullable|string|max:100',
            'bio' => 'nullable|string',
            'status' => 'nullable|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $imagePath = $comedian->image; // preserve existing image unless a new one is uploaded

            if ($request->hasFile('image')) {
                // Delete the old file first, if one exists
                if ($comedian->image) {
                    $oldImagePath = public_path('storage/' . $comedian->image);
                    if (file_exists($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                }

                // Stores under storage/app/public/images/comedians
                $filename = time() . '_' . uniqid() . '.' . $request->file('image')->getClientOriginalExtension();

                $imagePath = $request->file('image')->storeAs(
                    'images/comedians',
                    $filename,
                    'public'
                );
            }

            $comedian->fill($request->only(['name', 'tagline', 'bio', 'genre', 'status']));
            $comedian->image = $imagePath;
            $comedian->save();

            Log::info('Comedian updated', [
                'comedian_id' => $comedian->id,
                'name' => $comedian->name,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Comedian updated successfully',
                'data' => $comedian->fresh(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating comedian', [
                'comedian_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update comedian',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Delete a comedian
     */
    public function destroy($id)
    {
        $comedian = Comedian::find($id);
        Log::info('Comedian updated', [
            'comedian_id' => $comedian->id,
            'name' => $comedian->name,
        ]);



        // Delete the associated image file, if one exists
        if ($comedian->image) {
            $imagePath = public_path('storage/' . $comedian->image);
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }



        if (!$comedian) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        $comedian->delete();

        return response()->json(['success' => true, 'message' => 'Deleted successfully'], 200);
    }

    /**
     * Update comedian status
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $comedian = Comedian::find($id);

            if (!$comedian) {
                return response()->json([
                    'success' => false,
                    'message' => 'Comedian not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'status' => 'required|in:active,inactive',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $oldStatus = $comedian->status;
            $comedian->update(['status' => $request->status]);

            Log::info('Comedian status updated', [
                'comedian_id' => $comedian->id,
                'old_status' => $oldStatus,
                'new_status' => $request->status,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Comedian status updated successfully',
                'data' => $comedian->fresh(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating comedian status', [
                'comedian_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update status',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}