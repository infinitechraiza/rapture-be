<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gallery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class GalleryController extends Controller
{
    /**
     * GET /api/gallery
     */
    public function index(Request $request): JsonResponse
    {
        $query = Gallery::query()
            ->orderBy('sort_order')
            ->orderByDesc('date_created');

        if ($request->filled('category')) {
            $query->where('category', $request->string('category'));
        }

        if ($request->filled('status')) {
            $status = $request->string('status');
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        return response()->json(['data' => $query->get()]);
    }

    /**
     * POST /api/gallery
     * Accepts multipart/form-data: title, category, description, sort_order, is_active, image (file)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ]);

        try {
            $imagePath = null;
            if ($request->hasFile('image')) {
                // Stores under storage/app/public/images/gallery
                $filename = time() . '_' . uniqid() . '.' . $request->file('image')->getClientOriginalExtension();

                $imagePath = $request->file('image')->storeAs(
                    'images/gallery',
                    $filename,
                    'public'
                );
            }

            $item = Gallery::create([
                'user_id' => $request->user_id ?? auth()->id(),
                'title' => $validated['title'],
                'category' => $validated['category'] ?? null,
                'description' => $validated['description'] ?? null,
                'sort_order' => $validated['sort_order'] ?? 0,
                'is_active' => $request->boolean('is_active', true),
                'image' => $imagePath,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Gallery item created successfully',
                'data' => $item->fresh(),
            ], 201);

        } catch (\Exception $e) {
            Log::error('Gallery item creation failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create gallery item',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * GET /api/gallery/{id}
     */
    public function show(int $id): JsonResponse
    {
        $item = Gallery::find($id);

        if (!$item) {
            return response()->json(['message' => 'Gallery item not found.'], 404);
        }

        return response()->json(['data' => $item]);
    }

    /**
     * PUT/PATCH /api/gallery/{id}
     * Accepts multipart/form-data (Laravel needs _method=PUT spoofing from
     * the client when sending FormData with PUT — see the Next.js route).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $item = Gallery::find($id);

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Gallery item not found',
            ], 404);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ]);

        try {
            // Preserve existing image unless a new one is uploaded
            $imagePath = $item->image;

            if ($request->hasFile('image')) {
                // Delete the old file first, if one exists
                if ($item->image) {
                    $oldImagePath = public_path('storage/' . $item->image);
                    if (file_exists($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                }

                $filename = time() . '_' . uniqid() . '.' . $request->file('image')->getClientOriginalExtension();

                $imagePath = $request->file('image')->storeAs(
                    'images/gallery',
                    $filename,
                    'public'
                );
            }

            $item->fill([
                'title' => $validated['title'],
                'category' => $validated['category'] ?? null,
                'description' => $validated['description'] ?? null,
                'sort_order' => $validated['sort_order'] ?? $item->sort_order,
            ]);

            // Only touch is_active if the client actually sent it
            if ($request->has('is_active')) {
                $item->is_active = $request->boolean('is_active');
            }

            $item->image = $imagePath;
            $item->save();

            Log::info('Gallery item updated', [
                'gallery_id' => $item->id,
                'title' => $item->title,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Gallery item updated successfully',
                'data' => $item->fresh(),
            ]);

        } catch (\Exception $e) {
            Log::error('Gallery item update failed', [
                'gallery_id' => $id,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update gallery item',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * DELETE /api/gallery/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $item = Gallery::find($id);

        if (!$item) {
            return response()->json(['message' => 'Gallery item not found.'], 404);
        }

        // Delete the associated image file, if one exists
        if ($item->image) {
            $imagePath = public_path('storage/' . $item->image);
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }

        $item->delete();

        return response()->json(['message' => 'Gallery item deleted.']);
    }
}