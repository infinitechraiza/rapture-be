<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AboutSection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AboutController extends Controller
{
    /**
     * GET /api/about
     * Returns the about page content (story section + ordered value cards).
     * Equivalent to Next.js route.ts GET handler.
     */
    public function index(): JsonResponse
    {
        $section = AboutSection::with('values')
            ->where('is_active', true)
            ->latest()
            ->first();

        if (!$section) {
            return response()->json([
                'message' => 'About section not found.',
            ], 404);
        }

        return response()->json([
            'data' => $section,
        ]);
    }

    /**
     * POST /api/about
     * Creates the about section (used once, on first setup).
     * Equivalent to Next.js route.ts POST handler.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = $this->validator($request, isUpdate: false);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

      
        $section = AboutSection::create($validator->validated());

        return response()->json(['data' => $section->fresh('values')], 201);
    }

    /**
     * GET /api/about/{id}
     * Equivalent to Next.js [id]/route.ts GET handler.
     */
    public function show(int $id): JsonResponse
    {
        $section = AboutSection::with('values')->find($id);

        if (!$section) {
            return response()->json(['message' => 'About section not found.'], 404);
        }

        return response()->json(['data' => $section]);
    }

    /** 
     * PUT/PATCH /api/about/{id}
     * Equivalent to Next.js [id]/route.ts PUT handler.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $section = AboutSection::find($id);

        if (!$section) {
            return response()->json(['message' => 'About section not found.'], 404);
        }

        $validator = $this->validator($request, isUpdate: true);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $section->update($validator->validated());

        return response()->json(['data' => $section->fresh('values')]);
    }

    /**
     * DELETE /api/about/{id}
     * Equivalent to Next.js [id]/route.ts DELETE handler.
     */
    public function destroy(int $id): JsonResponse
    {
        $section = AboutSection::find($id);

        if (!$section) {
            return response()->json(['message' => 'About section not found.'], 404);
        }

        $section->delete();

        return response()->json(['message' => 'About section deleted.']);
    }

    /**
     * Validation rules trimmed to match the current admin form:
     * title, title highlight, two description paragraphs, and a stat label.
     * (Photo/badge fields and the old "eyebrow"/"stat_value" inputs were
     * removed from the UI; the underlying columns can stay in the schema
     * unused, or be dropped in a follow-up migration if desired.)
     */
    private function validator(Request $request, bool $isUpdate): \Illuminate\Validation\Validator
    {
        $rules = [
            'title' => ($isUpdate ? 'sometimes|' : '') . 'required|string|max:255',
            'title_highlight' => 'nullable|string|max:255',
            'description_primary' => 'nullable|string',
            'description_secondary' => 'nullable|string',
            'stat_label' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ];

        return Validator::make($request->all(), $rules);
    }
}