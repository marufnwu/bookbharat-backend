<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BundleDiscountRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BundleDiscountController extends Controller
{
    /**
     * Get all bundle discount rules
     */
    public function index(Request $request)
    {
        $query = BundleDiscountRule::with('category');
        
        // Filter by active status
        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }
        
        // Filter by category
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        
        // Sort by priority
        $query->orderBy('priority', 'desc')->orderBy('min_products', 'asc');
        
        $rules = $request->get('per_page') 
            ? $query->paginate($request->get('per_page', 20))
            : $query->get();
        
        return response()->json([
            'success' => true,
            'data' => $rules
        ]);
    }
    
    /**
     * Get active rules for frontend
     */
    public function getActiveRules()
    {
        $rules = BundleDiscountRule::active()
            ->currentlyValid()
            ->orderBy('min_products', 'asc')
            ->get()
            ->map(function ($rule) {
                return [
                    'id' => $rule->id,
                    'name' => $rule->name,
                    'min_products' => $rule->min_products,
                    'max_products' => $rule->max_products,
                    'discount' => $rule->getFormattedDiscount(),
                    'discount_percentage' => $rule->discount_percentage,
                    'discount_type' => $rule->discount_type,
                    'description' => $rule->description,
                    'category_id' => $rule->category_id,
                    'valid_from' => $rule->valid_from,
                    'valid_until' => $rule->valid_until,
                ];
            });
        
        return response()->json([
            'success' => true,
            'data' => $rules
        ]);
    }
    
    /**
     * Get a single discount rule
     */
    public function show($id)
    {
        $rule = BundleDiscountRule::with('category')->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $rule
        ]);
    }
    
    /**
     * Create a new discount rule
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'min_products' => 'required|integer|min:2',
            'max_products' => 'nullable|integer|min:2|gte:min_products',
            'discount_percentage' => 'required_if:discount_type,percentage|numeric|min:0|max:100',
            'fixed_discount' => 'required_if:discount_type,fixed|numeric|min:0',
            'discount_type' => 'required|in:percentage,fixed',
            'category_id' => 'nullable|exists:categories,id',
            'customer_tier' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'priority' => 'integer',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'conditions' => 'nullable|array',
            'description' => 'nullable|string',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        $rule = BundleDiscountRule::create($request->all());
        
        return response()->json([
            'success' => true,
            'message' => 'Bundle discount rule created successfully',
            'data' => $rule
        ], 201);
    }
    
    /**
     * Update a discount rule
     */
    public function update(Request $request, $id)
    {
        $rule = BundleDiscountRule::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'min_products' => 'integer|min:2',
            'max_products' => 'nullable|integer|min:2|gte:min_products',
            'discount_percentage' => 'required_if:discount_type,percentage|numeric|min:0|max:100',
            'fixed_discount' => 'required_if:discount_type,fixed|numeric|min:0',
            'discount_type' => 'in:percentage,fixed',
            'category_id' => 'nullable|exists:categories,id',
            'customer_tier' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'priority' => 'integer',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'conditions' => 'nullable|array',
            'description' => 'nullable|string',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        $rule->update($request->all());
        
        return response()->json([
            'success' => true,
            'message' => 'Bundle discount rule updated successfully',
            'data' => $rule
        ]);
    }
    
    /**
     * Toggle rule active status
     */
    public function toggleActive($id)
    {
        $rule = BundleDiscountRule::findOrFail($id);
        $rule->is_active = !$rule->is_active;
        $rule->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Rule status updated successfully',
            'data' => $rule
        ]);
    }
    
    /**
     * Delete a discount rule
     */
    public function destroy($id)
    {
        $rule = BundleDiscountRule::findOrFail($id);
        $rule->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Bundle discount rule deleted successfully'
        ]);
    }
    
    /**
     * Get bundle price preview with current rules
     */
    public function previewDiscount(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_ids' => 'required|array|min:2',
            'product_ids.*' => 'exists:products,id',
            'customer_tier' => 'nullable|string',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        $products = \App\Models\Product::whereIn('id', $request->product_ids)->get();
        
        if ($products->count() < 2) {
            return response()->json([
                'success' => false,
                'message' => 'At least 2 products required for bundle discount'
            ], 400);
        }
        
        $service = new \App\Services\ProductRecommendationService();
        $bundleData = $service->calculateBundlePrice(
            $products->first(),
            $products->skip(1)
        );
        
        return response()->json([
            'success' => true,
            'data' => $bundleData
        ]);
    }
}