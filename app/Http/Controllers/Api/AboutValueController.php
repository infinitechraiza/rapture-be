<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AboutValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AboutValueController extends Controller
{
    /**
     * GET /api/about/values
     */
    public function index(Request $request): JsonResponse
    {
        $query = AboutValue::query()->orderBy('sort_order');

        if ($request->filled('about_section_id')) {
            $query->where('about_section_id', $request->integer('about_section_id'));
        }

        return response()->json(['data' => $query->get()]);
    }

    /**
     * POST /api/about/values
     */
    public function store(Request $request): JsonResponse
    {
        $validator = $this->validator($request, isUpdate: false);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $data['is_active'] = true; // Ensure the first section is active by default


        if (!isset($data['sort_order'])) {
            $data['sort_order'] = AboutValue::where('about_section_id', $data['about_section_id'] ?? null)
                ->max('sort_order') + 1;
        }

        $value = AboutValue::create($data);

        return response()->json(['data' => $value], 201);
    }

    /**
     * GET /api/about/values/{id}
     */
    public function show(int $id): JsonResponse
    {
        $value = AboutValue::find($id);

        if (!$value) {
            return response()->json(['message' => 'Value card not found3333.'], 404);
        }

        return response()->json(['data' => $value]);
    }

    /**
     * PUT/PATCH /api/about/values/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $value = AboutValue::find($id);

        if (!$value) {
            return response()->json(['message' => 'Value card not found222.'], 404);
        }

        $validator = $this->validator($request, isUpdate: true);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $value->update($validator->validated());

        return response()->json(['data' => $value->fresh()]);
    }

    /**
     * DELETE /api/about/values/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $value = AboutValue::find($id);

        if (!$value) {
            return response()->json(['message' => 'Value card not found1111.'], 404);
        }

        $value->delete();

        return response()->json(['message' => 'Value card deleted.']);
    }

    private function validator(Request $request, bool $isUpdate): \Illuminate\Validation\Validator
    {
        $rules = [
            'about_section_id' => 'nullable|exists:about_sections,id',
            'icon' => ($isUpdate ? 'sometimes|' : '') . 'required|string|max:100',
            'title' => ($isUpdate ? 'sometimes|' : '') . 'required|string|max:255',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ];

        return Validator::make($request->all(), $rules);
    }
}