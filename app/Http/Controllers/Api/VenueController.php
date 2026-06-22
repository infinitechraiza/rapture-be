<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Exception;

class VenueController extends Controller
{
    public function index(Request $request)
    {
        try {
            Log::info('Venue index request', [
                'all_params' => $request->all(),
                'sort_by' => $request->input('sort_by'),
                'sort_order' => $request->input('sort_order'),
            ]);

            $query = Venue::query();

            // Search functionality
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('full_description', 'like', "%{$search}%")
                        ->orWhere('size', 'like', "%{$search}%");
                });
            }

            // Sorting functionality
            $sortBy = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');

            // Whitelist allowed sort fields for security
            $allowedSortFields = ['name', 'price_per_day', 'max_guests', 'size', 'created_at', 'id'];
            $sortBy = in_array($sortBy, $allowedSortFields) ? $sortBy : 'created_at';

            // Validate sort order
            $sortOrder = in_array(strtolower($sortOrder), ['asc', 'desc']) ? strtolower($sortOrder) : 'desc';

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

            $venues = $query->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();

            // Calculate from and to
            $from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
            $to = min($page * $perPage, $total);

            // Log first venue for verification
            if ($venues->count() > 0) {
                Log::info('First venue after sort', [
                    'name' => $venues[0]->name,
                    'price_per_day' => $venues[0]->price_per_day,
                    'max_guests' => $venues[0]->max_guests,
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'data' => $venues,
                    'total' => $total,
                    'per_page' => (int) $perPage,
                    'current_page' => (int) $page,
                    'last_page' => (int) ceil($total / $perPage),
                    'from' => $from,
                    'to' => $to,
                ],
                'debug' => config('app.debug') ? [
                    'sort_by' => $sortBy,
                    'sort_order' => $sortOrder,
                ] : null,
            ]);
        } catch (Exception $e) {
            Log::error('Error fetching venues', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching venues',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            Log::info('Creating venue', [
                'name' => $request->input('name'),
                'has_image' => $request->hasFile('image')
            ]);

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'full_description' => 'required|string',
                'price_per_day' => 'required|numeric|min:0',
                'max_guests' => 'required|integer|min:1',
                'size' => 'required|string',
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
                $image->move(public_path('uploads/venues'), $imageName);
                $validated['image'] = '/uploads/venues/' . $imageName;
                
                Log::info('Image uploaded', ['path' => $validated['image']]);
            }

            // Handle multiple images upload
            if ($request->hasFile('images')) {
                $imagesPaths = [];
                foreach ($request->file('images') as $img) {
                    $imgName = time() . '_' . Str::random(10) . '.' . $img->getClientOriginalExtension();
                    $img->move(public_path('uploads/venues'), $imgName);
                    $imagesPaths[] = '/uploads/venues/' . $imgName;
                }
                $validated['images'] = $imagesPaths;
            }

            // Handle panorama images upload
            if ($request->hasFile('panorama_images')) {
                $panoramas = [];
                $panoramaNames = $request->input('panorama_names', []);

                foreach ($request->file('panorama_images') as $index => $panoramaImg) {
                    $panoramaName = time() . '_panorama_' . Str::random(10) . '.' . $panoramaImg->getClientOriginalExtension();
                    $panoramaImg->move(public_path('uploads/venues/panoramas'), $panoramaName);

                    $panoramas[] = [
                        'id' => Str::slug($panoramaNames[$index] ?? 'view-' . ($index + 1)),
                        'name' => $panoramaNames[$index] ?? 'View ' . ($index + 1),
                        'panoramaUrl' => '/uploads/venues/panoramas/' . $panoramaName,
                        'thumbnail' => '/uploads/venues/panoramas/' . $panoramaName,
                    ];
                }
                $validated['panoramas'] = $panoramas;
            }

            $venue = Venue::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Venue created successfully',
                'data' => $venue
            ], 201);
        } catch (Exception $e) {
            Log::error('Error creating venue', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error creating venue',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $venue = Venue::findOrFail($id);
            return response()->json([
                'success' => true,
                'data' => $venue
            ]);
        } catch (Exception $e) {
            Log::error('Error fetching venue', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Venue not found',
                'error' => config('app.debug') ? $e->getMessage() : 'Not found'
            ], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $venue = Venue::findOrFail($id);

            Log::info('Updating venue', [
                'id' => $id,
                'name' => $request->input('name'),
                'has_image' => $request->hasFile('image'),
                'current_image' => $venue->image,
                'all_files' => array_keys($request->allFiles()),
            ]);

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'full_description' => 'sometimes|required|string',
                'price_per_day' => 'sometimes|required|numeric|min:0',
                'max_guests' => 'sometimes|required|integer|min:1',
                'size' => 'sometimes|required|string',
                'amenities' => 'sometimes|required|array',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
                'images.*' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
                'panorama_images.*' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
                'panorama_names' => 'nullable|array',
            ]);

            // Handle main image upload
            if ($request->hasFile('image')) {
                Log::info('New image detected for venue update');
                
                // Delete old image
                if ($venue->image && file_exists(public_path($venue->image))) {
                    unlink(public_path($venue->image));
                    Log::info('Old image deleted', ['path' => $venue->image]);
                }

                $image = $request->file('image');
                $imageName = time() . '_' . Str::slug($request->name ?? $venue->name) . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('uploads/venues'), $imageName);
                $validated['image'] = '/uploads/venues/' . $imageName;
                
                Log::info('New image uploaded', ['path' => $validated['image']]);
            }

            // Handle multiple images upload
            if ($request->hasFile('images')) {
                // Delete old images
                if ($venue->images) {
                    foreach ($venue->images as $oldImg) {
                        if (file_exists(public_path($oldImg))) {
                            unlink(public_path($oldImg));
                        }
                    }
                }

                $imagesPaths = [];
                foreach ($request->file('images') as $img) {
                    $imgName = time() . '_' . Str::random(10) . '.' . $img->getClientOriginalExtension();
                    $img->move(public_path('uploads/venues'), $imgName);
                    $imagesPaths[] = '/uploads/venues/' . $imgName;
                }
                $validated['images'] = $imagesPaths;
            }

            // Handle panorama images upload
            if ($request->hasFile('panorama_images')) {
                // Delete old panorama images
                if ($venue->panoramas) {
                    foreach ($venue->panoramas as $oldPanorama) {
                        if (isset($oldPanorama['panoramaUrl']) && file_exists(public_path($oldPanorama['panoramaUrl']))) {
                            unlink(public_path($oldPanorama['panoramaUrl']));
                        }
                    }
                }

                $panoramas = [];
                $panoramaNames = $request->input('panorama_names', []);

                foreach ($request->file('panorama_images') as $index => $panoramaImg) {
                    $panoramaName = time() . '_panorama_' . Str::random(10) . '.' . $panoramaImg->getClientOriginalExtension();
                    $panoramaImg->move(public_path('uploads/venues/panoramas'), $panoramaName);

                    $panoramas[] = [
                        'id' => Str::slug($panoramaNames[$index] ?? 'view-' . ($index + 1)),
                        'name' => $panoramaNames[$index] ?? 'View ' . ($index + 1),
                        'panoramaUrl' => '/uploads/venues/panoramas/' . $panoramaName,
                        'thumbnail' => '/uploads/venues/panoramas/' . $panoramaName,
                    ];
                }
                $validated['panoramas'] = $panoramas;
            }

            $venue->update($validated);
            
            Log::info('Venue updated successfully', [
                'id' => $id,
                'updated_image' => $venue->image
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Venue updated successfully',
                'data' => $venue->fresh() // Get fresh data from database
            ]);
        } catch (Exception $e) {
            Log::error('Error updating venue', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error updating venue',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $venue = Venue::findOrFail($id);

            // Delete images
            if ($venue->image && file_exists(public_path($venue->image))) {
                unlink(public_path($venue->image));
            }

            if ($venue->images) {
                foreach ($venue->images as $img) {
                    if (file_exists(public_path($img))) {
                        unlink(public_path($img));
                    }
                }
            }

            // Delete panorama images
            if ($venue->panoramas) {
                foreach ($venue->panoramas as $panorama) {
                    if (isset($panorama['panoramaUrl']) && file_exists(public_path($panorama['panoramaUrl']))) {
                        unlink(public_path($panorama['panoramaUrl']));
                    }
                }
            }

            $venue->delete();

            return response()->json([
                'success' => true,
                'message' => 'Venue deleted successfully'
            ]);
        } catch (Exception $e) {
            Log::error('Error deleting venue', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error deleting venue',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}