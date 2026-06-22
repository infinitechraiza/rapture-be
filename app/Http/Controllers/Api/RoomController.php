<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class RoomController extends Controller
{
    public function index(Request $request)
    {
        // Log incoming request for debugging
        Log::info('Room index request', [
            'all_params' => $request->all(),
            'sort_by' => $request->input('sort_by'),
            'sort_order' => $request->input('sort_order'),
        ]);

        $query = Room::query();

        // Search functionality
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('full_description', 'like', "%{$search}%")
                    ->orWhere('bed_type', 'like', "%{$search}%")
                    ->orWhere('size', 'like', "%{$search}%");
            });
        }

        // Sorting functionality
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');

        // Whitelist allowed sort fields for security
        $allowedSortFields = ['name', 'price', 'capacity', 'size', 'bed_type', 'created_at', 'id'];
        $sortBy = in_array($sortBy, $allowedSortFields) ? $sortBy : 'created_at';

        // Validate sort order
        $sortOrder = in_array(strtolower($sortOrder), ['asc', 'desc']) ? strtolower($sortOrder) : 'desc';

        // Log sorting params
        Log::info('Applying sort', [
            'sort_by' => $sortBy,
            'sort_order' => $sortOrder,
        ]);

        // Apply sorting
        $query->orderBy($sortBy, $sortOrder);

        // Get total count before pagination
        $total = $query->count();

        // Pagination
        $perPage = $request->get('per_page', 10);
        $page = $request->get('page', 1);

        $rooms = $query->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        // Calculate from and to
        $from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to = min($page * $perPage, $total);

        // Log first room for verification
        if ($rooms->count() > 0) {
            Log::info('First room after sort', [
                'name' => $rooms[0]->name,
                'price' => $rooms[0]->price,
                'capacity' => $rooms[0]->capacity,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'data' => $rooms,
                'total' => $total,
                'per_page' => (int) $perPage,
                'current_page' => (int) $page,
                'last_page' => (int) ceil($total / $perPage),
                'from' => $from,
                'to' => $to,
            ],
            // Add debug info in development
            'debug' => config('app.debug') ? [
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ] : null,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'full_description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'capacity' => 'required|integer|min:1',
            'size' => 'required|string',
            'bed_type' => 'required|string',
            'amenities' => 'required|array',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'panorama_images.*' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'panorama_names' => 'nullable|array',
        ]);

        // Handle main image upload
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '_' . Str::slug($request->name) . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('uploads/rooms'), $imageName);
            $validated['image'] = '/uploads/rooms/' . $imageName;
        }

        // Handle multiple images upload
        if ($request->hasFile('images')) {
            $imagesPaths = [];
            foreach ($request->file('images') as $img) {
                $imgName = time() . '_' . Str::random(10) . '.' . $img->getClientOriginalExtension();
                $img->move(public_path('uploads/rooms'), $imgName);
                $imagesPaths[] = '/uploads/rooms/' . $imgName;
            }
            $validated['images'] = $imagesPaths;
        }

        // Handle panorama images upload
        if ($request->hasFile('panorama_images')) {
            $panoramas = [];
            $panoramaNames = $request->input('panorama_names', []);

            foreach ($request->file('panorama_images') as $index => $panoramaImg) {
                $panoramaName = time() . '_panorama_' . Str::random(10) . '.' . $panoramaImg->getClientOriginalExtension();
                $panoramaImg->move(public_path('uploads/rooms/panoramas'), $panoramaName);

                $panoramas[] = [
                    'id' => Str::slug($panoramaNames[$index] ?? 'view-' . ($index + 1)),
                    'name' => $panoramaNames[$index] ?? 'View ' . ($index + 1),
                    'panoramaUrl' => '/uploads/rooms/panoramas/' . $panoramaName,
                    'thumbnail' => '/uploads/rooms/panoramas/' . $panoramaName,
                ];
            }
            $validated['panoramas'] = $panoramas;
        }

        $room = Room::create($validated);

        return response()->json([
            'message' => 'Room created successfully',
            'data' => $room
        ], 201);
    }

    public function show($id)
    {
        $room = Room::findOrFail($id);
        return response()->json(['data' => $room]);
    }

    public function update(Request $request, $id)
    {
        $room = Room::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'full_description' => 'sometimes|required|string',
            'price' => 'sometimes|required|numeric|min:0',
            'capacity' => 'sometimes|required|integer|min:1',
            'size' => 'sometimes|required|string',
            'bed_type' => 'sometimes|required|string',
            'amenities' => 'sometimes|required|array',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'panorama_images.*' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'panorama_names' => 'nullable|array',
        ]);

        // Handle main image upload
        if ($request->hasFile('image')) {
            // Delete old image
            if ($room->image && file_exists(public_path($room->image))) {
                unlink(public_path($room->image));
            }

            $image = $request->file('image');
            $imageName = time() . '_' . Str::slug($request->name ?? $room->name) . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('uploads/rooms'), $imageName);
            $validated['image'] = '/uploads/rooms/' . $imageName;
        }

        // Handle multiple images upload
        if ($request->hasFile('images')) {
            // Delete old images
            if ($room->images) {
                foreach ($room->images as $oldImg) {
                    if (file_exists(public_path($oldImg))) {
                        unlink(public_path($oldImg));
                    }
                }
            }

            $imagesPaths = [];
            foreach ($request->file('images') as $img) {
                $imgName = time() . '_' . Str::random(10) . '.' . $img->getClientOriginalExtension();
                $img->move(public_path('uploads/rooms'), $imgName);
                $imagesPaths[] = '/uploads/rooms/' . $imgName;
            }
            $validated['images'] = $imagesPaths;
        }

        // Handle panorama images upload
        if ($request->hasFile('panorama_images')) {
            // Delete old panorama images
            if ($room->panoramas) {
                foreach ($room->panoramas as $oldPanorama) {
                    if (isset($oldPanorama['panoramaUrl']) && file_exists(public_path($oldPanorama['panoramaUrl']))) {
                        unlink(public_path($oldPanorama['panoramaUrl']));
                    }
                }
            }

            $panoramas = [];
            $panoramaNames = $request->input('panorama_names', []);

            foreach ($request->file('panorama_images') as $index => $panoramaImg) {
                $panoramaName = time() . '_panorama_' . Str::random(10) . '.' . $panoramaImg->getClientOriginalExtension();
                $panoramaImg->move(public_path('uploads/rooms/panoramas'), $panoramaName);

                $panoramas[] = [
                    'id' => Str::slug($panoramaNames[$index] ?? 'view-' . ($index + 1)),
                    'name' => $panoramaNames[$index] ?? 'View ' . ($index + 1),
                    'panoramaUrl' => '/uploads/rooms/panoramas/' . $panoramaName,
                    'thumbnail' => '/uploads/rooms/panoramas/' . $panoramaName,
                ];
            }
            $validated['panoramas'] = $panoramas;
        }

        $room->update($validated);

        return response()->json([
            'message' => 'Room updated successfully',
            'data' => $room
        ]);
    }

    public function destroy($id)
    {
        $room = Room::findOrFail($id);

        // Delete images
        if ($room->image && file_exists(public_path($room->image))) {
            unlink(public_path($room->image));
        }

        if ($room->images) {
            foreach ($room->images as $img) {
                if (file_exists(public_path($img))) {
                    unlink(public_path($img));
                }
            }
        }

        // Delete panorama images
        if ($room->panoramas) {
            foreach ($room->panoramas as $panorama) {
                if (isset($panorama['panoramaUrl']) && file_exists(public_path($panorama['panoramaUrl']))) {
                    unlink(public_path($panorama['panoramaUrl']));
                }
            }
        }

        $room->delete();

        return response()->json([
            'message' => 'Room deleted successfully'
        ]);
    }
}