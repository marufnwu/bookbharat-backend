<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContentBlock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ContentBlockController extends Controller
{
    /**
     * Get content block by key (Public API)
     */
    public function getByKey(Request $request, string $key)
    {
        $language = $request->get('language', 'en');
        $fallbackLanguage = $request->get('fallback', 'en');

        $content = ContentBlock::getByKey($key, $language, $fallbackLanguage);

        return response()->json([
            'success' => true,
            'key' => $key,
            'language' => $language,
            'content' => $content
        ]);
    }

    /**
     * Get all content blocks by category (Public API)
     */
    public function getByCategory(Request $request, string $category)
    {
        $language = $request->get('language', 'en');

        $blocks = ContentBlock::getByCategory($category, $language);

        return response()->json([
            'success' => true,
            'category' => $category,
            'language' => $language,
            'blocks' => $blocks
        ]);
    }

    /**
     * List all content blocks (Admin API)
     */
    public function index(Request $request)
    {
        $query = ContentBlock::query();

        // Filter by category if provided
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        // Filter by language if provided
        if ($request->has('language')) {
            $query->where('language', $request->language);
        }

        // Filter by is_active
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $blocks = $query->orderBy('category')->orderBy('order')->get();

        return response()->json([
            'success' => true,
            'blocks' => $blocks
        ]);
    }

    /**
     * Get single content block (Admin API)
     */
    public function show($id)
    {
        $block = ContentBlock::findOrFail($id);

        return response()->json([
            'success' => true,
            'block' => $block
        ]);
    }

    /**
     * Create content block (Admin API)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'key' => 'required|string|unique:content_blocks,key',
            'content' => 'required|string',
            'language' => 'required|string|size:2',
            'category' => 'nullable|string',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'order' => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $block = ContentBlock::create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Content block created successfully',
            'block' => $block
        ], 201);
    }

    /**
     * Update content block (Admin API)
     */
    public function update(Request $request, $id)
    {
        $block = ContentBlock::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'key' => 'sometimes|required|string|unique:content_blocks,key,' . $id . ',id,language,' . $block->language,
            'content' => 'sometimes|required|string',
            'language' => 'sometimes|required|string|size:2',
            'category' => 'nullable|string',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'order' => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $block->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Content block updated successfully',
            'block' => $block
        ]);
    }

    /**
     * Delete content block (Admin API)
     */
    public function destroy($id)
    {
        $block = ContentBlock::findOrFail($id);
        $block->delete();

        return response()->json([
            'success' => true,
            'message' => 'Content block deleted successfully'
        ]);
    }
}
