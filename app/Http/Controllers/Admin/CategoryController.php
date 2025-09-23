<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Category::withCount(['products']);

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('parent_id')) {
            $query->where('parent_id', $request->parent_id);
        }

        $categories = $query->orderBy('sort_order')->paginate($request->per_page ?? 10);

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    public function tree()
    {
        $categories = Category::whereNull('parent_id')
            ->with(['children' => function ($query) {
                $query->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    public function show(Category $category)
    {
        $category->load(['parent', 'children', 'products']);

        return response()->json([
            'success' => true,
            'data' => $category
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'description' => 'nullable|string',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean',
            'image' => 'nullable|image|max:2048',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500'
        ]);

        DB::beginTransaction();
        try {
            $data = $request->except('image');
            $data['slug'] = Str::slug($request->name);

            // Ensure unique slug
            $count = 1;
            $originalSlug = $data['slug'];
            while (Category::where('slug', $data['slug'])->exists()) {
                $data['slug'] = $originalSlug . '-' . $count++;
            }

            if ($request->hasFile('image')) {
                $data['image'] = $request->file('image')->store('categories', 'public');
            }

            $category = Category::create($data);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Category created successfully',
                'data' => $category
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create category: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, Category $category)
    {

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'parent_id' => 'nullable|exists:categories,id|not_in:' . $category->id,
            'description' => 'nullable|string',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean',
            'image' => 'nullable|image|max:2048',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500'
        ]);

        // Prevent setting itself as parent
        if ($request->parent_id == $category->id) {
            return response()->json([
                'success' => false,
                'message' => 'A category cannot be its own parent'
            ], 422);
        }

        // Prevent setting a child as parent
        if ($request->parent_id && $this->isDescendant($category->id, $request->parent_id)) {
            return response()->json([
                'success' => false,
                'message' => 'A category cannot have its descendant as parent'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $data = $request->except('image');

            if ($request->filled('name') && $request->name !== $category->name) {
                $data['slug'] = Str::slug($request->name);

                // Ensure unique slug
                $count = 1;
                $originalSlug = $data['slug'];
                while (Category::where('slug', $data['slug'])->where('id', '!=', $category->id)->exists()) {
                    $data['slug'] = $originalSlug . '-' . $count++;
                }
            }

            if ($request->hasFile('image')) {
                // Delete old image
                if ($category->image) {
                    Storage::disk('public')->delete($category->image);
                }
                $data['image'] = $request->file('image')->store('categories', 'public');
            }

            $category->update($data);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Category updated successfully',
                'data' => $category
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update category: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Category $category)
    {

        // Check if category has products
        if ($category->products()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete category with products. Please reassign or delete products first.'
            ], 422);
        }

        // Check if category has children
        if ($category->children()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete category with subcategories. Please delete subcategories first.'
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Delete image if exists
            if ($category->image) {
                Storage::disk('public')->delete($category->image);
            }

            $category->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Category deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete category: ' . $e->getMessage()
            ], 500);
        }
    }

    public function move(Request $request, Category $category)
    {

        $request->validate([
            'parent_id' => 'nullable|exists:categories,id|not_in:' . $category->id,
            'sort_order' => 'integer|min:0'
        ]);

        // Prevent setting itself as parent
        if ($request->parent_id == $category->id) {
            return response()->json([
                'success' => false,
                'message' => 'A category cannot be its own parent'
            ], 422);
        }

        // Prevent setting a child as parent
        if ($request->parent_id && $this->isDescendant($category->id, $request->parent_id)) {
            return response()->json([
                'success' => false,
                'message' => 'A category cannot have its descendant as parent'
            ], 422);
        }

        $category->update([
            'parent_id' => $request->parent_id,
            'sort_order' => $request->sort_order ?? $category->sort_order
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Category moved successfully',
            'data' => $category
        ]);
    }

    public function uploadImage(Request $request, Category $category)
    {

        $request->validate([
            'image' => 'required|image|max:2048'
        ]);

        try {
            // Delete old image
            if ($category->image) {
                Storage::disk('public')->delete($category->image);
            }

            $imagePath = $request->file('image')->store('categories', 'public');
            $category->update(['image' => $imagePath]);

            return response()->json([
                'success' => true,
                'message' => 'Image uploaded successfully',
                'data' => [
                    'image' => $imagePath,
                    'url' => Storage::url($imagePath)
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload image: ' . $e->getMessage()
            ], 500);
        }
    }

    private function isDescendant($parentId, $childId)
    {
        $category = Category::find($childId);

        while ($category) {
            if ($category->parent_id == $parentId) {
                return true;
            }
            $category = $category->parent;
        }

        return false;
    }
}