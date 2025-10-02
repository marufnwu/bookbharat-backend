<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BundleDiscountRule;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BundleDiscountRuleController extends Controller
{
    /**
     * Get all bundle discount rules with filters
     */
    public function index(Request $request)
    {
        $query = BundleDiscountRule::with('category');

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filter by category
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by product count
        if ($request->filled('min_products')) {
            $query->where('min_products', '>=', $request->min_products);
        }

        // Filter by discount type
        if ($request->filled('discount_type')) {
            $query->where('discount_type', $request->discount_type);
        }

        // Search by name
        if ($request->filled('search')) {
            $query->where('name', 'LIKE', "%{$request->search}%");
        }

        // Sort
        $sortBy = $request->get('sort_by', 'priority');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 20);
        $rules = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'rules' => $rules
        ]);
    }

    /**
     * Get a single bundle discount rule
     */
    public function show($id)
    {
        $rule = BundleDiscountRule::with('category')->findOrFail($id);

        return response()->json([
            'success' => true,
            'rule' => $rule
        ]);
    }

    /**
     * Create a new bundle discount rule
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'discount_type' => 'required|in:percentage,fixed',
            'discount_percentage' => 'required_if:discount_type,percentage|nullable|numeric|min:0|max:100',
            'discount_amount' => 'required_if:discount_type,fixed|nullable|numeric|min:0',
            'min_products' => 'nullable|integer|min:2',
            'max_products' => 'nullable|integer|min:2',
            'category_id' => 'nullable|exists:categories,id',
            'customer_tier' => 'nullable|string',
            'min_order_value' => 'nullable|numeric|min:0',
            'max_discount_cap' => 'nullable|numeric|min:0',
            'priority' => 'nullable|integer|min:0|max:100',
            'conditions' => 'nullable|array',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after:valid_from',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $rule = BundleDiscountRule::create([
                'name' => $request->name,
                'description' => $request->description,
                'discount_type' => $request->discount_type,
                'discount_percentage' => $request->discount_percentage,
                'discount_amount' => $request->discount_amount,
                'min_products' => $request->min_products ?? 2,
                'max_products' => $request->max_products,
                'category_id' => $request->category_id,
                'customer_tier' => $request->customer_tier,
                'min_order_value' => $request->min_order_value,
                'max_discount_cap' => $request->max_discount_cap,
                'priority' => $request->priority ?? 50,
                'conditions' => $request->conditions,
                'valid_from' => $request->valid_from,
                'valid_until' => $request->valid_until,
                'is_active' => $request->get('is_active', true),
            ]);

            // Clear cache
            \Cache::flush();

            return response()->json([
                'success' => true,
                'message' => 'Bundle discount rule created successfully',
                'rule' => $rule->load('category')
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Failed to create bundle discount rule', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create rule: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an existing bundle discount rule
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'discount_type' => 'in:percentage,fixed',
            'discount_percentage' => 'required_if:discount_type,percentage|nullable|numeric|min:0|max:100',
            'discount_amount' => 'required_if:discount_type,fixed|nullable|numeric|min:0',
            'min_products' => 'nullable|integer|min:2',
            'max_products' => 'nullable|integer|min:2',
            'category_id' => 'nullable|exists:categories,id',
            'customer_tier' => 'nullable|string',
            'min_order_value' => 'nullable|numeric|min:0',
            'max_discount_cap' => 'nullable|numeric|min:0',
            'priority' => 'nullable|integer|min:0|max:100',
            'conditions' => 'nullable|array',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $rule = BundleDiscountRule::findOrFail($id);

            $rule->update($request->only([
                'name',
                'description',
                'discount_type',
                'discount_percentage',
                'discount_amount',
                'min_products',
                'max_products',
                'category_id',
                'customer_tier',
                'min_order_value',
                'max_discount_cap',
                'priority',
                'conditions',
                'valid_from',
                'valid_until',
                'is_active',
            ]));

            // Clear cache
            \Cache::flush();

            return response()->json([
                'success' => true,
                'message' => 'Bundle discount rule updated successfully',
                'rule' => $rule->load('category')
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to update bundle discount rule', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update rule: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a bundle discount rule
     */
    public function destroy($id)
    {
        try {
            $rule = BundleDiscountRule::findOrFail($id);
            $rule->delete();

            // Clear cache
            \Cache::flush();

            return response()->json([
                'success' => true,
                'message' => 'Bundle discount rule deleted successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to delete bundle discount rule', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete rule: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle active status of a rule
     */
    public function toggleActive($id)
    {
        try {
            $rule = BundleDiscountRule::findOrFail($id);
            $rule->is_active = !$rule->is_active;
            $rule->save();

            // Clear cache
            \Cache::flush();

            return response()->json([
                'success' => true,
                'message' => 'Rule status updated successfully',
                'rule' => $rule->load('category')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get rule statistics
     */
    public function statistics()
    {
        try {
            $stats = [
                'total_rules' => BundleDiscountRule::count(),
                'active_rules' => BundleDiscountRule::where('is_active', true)->count(),
                'inactive_rules' => BundleDiscountRule::where('is_active', false)->count(),
                'percentage_rules' => BundleDiscountRule::where('discount_type', 'percentage')->count(),
                'fixed_rules' => BundleDiscountRule::where('discount_type', 'fixed')->count(),
                'expired_rules' => BundleDiscountRule::where('valid_until', '<', now())->count(),
                'category_specific_rules' => BundleDiscountRule::whereNotNull('category_id')->count(),
                'customer_tier_rules' => BundleDiscountRule::whereNotNull('customer_tier')->count(),
            ];

            // Most used rules (by priority)
            $topRules = BundleDiscountRule::with('category')
                ->where('is_active', true)
                ->orderBy('priority', 'desc')
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'statistics' => $stats,
                'top_rules' => $topRules
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test a rule against sample data
     */
    public function testRule(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'product_count' => 'required|integer|min:2',
            'total_amount' => 'required|numeric|min:0',
            'category_id' => 'nullable|exists:categories,id',
            'customer_tier' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $rule = BundleDiscountRule::findOrFail($id);

            // Check if rule would apply
            $applies = true;
            $reasons = [];

            if ($rule->min_products && $request->product_count < $rule->min_products) {
                $applies = false;
                $reasons[] = "Minimum {$rule->min_products} products required";
            }

            if ($rule->max_products && $request->product_count > $rule->max_products) {
                $applies = false;
                $reasons[] = "Maximum {$rule->max_products} products allowed";
            }

            if ($rule->min_order_value && $request->total_amount < $rule->min_order_value) {
                $applies = false;
                $reasons[] = "Minimum order value ₹{$rule->min_order_value} required";
            }

            if ($rule->category_id && $request->category_id != $rule->category_id) {
                $applies = false;
                $reasons[] = "Rule only applies to specific category";
            }

            if ($rule->customer_tier && $request->customer_tier != $rule->customer_tier) {
                $applies = false;
                $reasons[] = "Rule only applies to {$rule->customer_tier} tier";
            }

            if (!$rule->is_active) {
                $applies = false;
                $reasons[] = "Rule is not active";
            }

            if ($rule->valid_from && now() < $rule->valid_from) {
                $applies = false;
                $reasons[] = "Rule not yet valid";
            }

            if ($rule->valid_until && now() > $rule->valid_until) {
                $applies = false;
                $reasons[] = "Rule has expired";
            }

            // Calculate discount if applicable
            $discount = 0;
            if ($applies) {
                $discount = $rule->calculateDiscount($request->total_amount);
                if ($rule->max_discount_cap && $discount > $rule->max_discount_cap) {
                    $discount = $rule->max_discount_cap;
                }
            }

            return response()->json([
                'success' => true,
                'applies' => $applies,
                'reasons' => $reasons,
                'discount_amount' => round($discount, 2),
                'final_amount' => round($request->total_amount - $discount, 2),
                'savings' => round($discount, 2),
                'discount_percentage' => $request->total_amount > 0
                    ? round(($discount / $request->total_amount) * 100, 2)
                    : 0,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to test rule: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Duplicate a rule
     */
    public function duplicate($id)
    {
        try {
            $originalRule = BundleDiscountRule::findOrFail($id);

            $newRule = $originalRule->replicate();
            $newRule->name = $originalRule->name . ' (Copy)';
            $newRule->is_active = false; // Deactivate copy by default
            $newRule->save();

            return response()->json([
                'success' => true,
                'message' => 'Rule duplicated successfully',
                'rule' => $newRule->load('category')
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to duplicate rule: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available categories for rules
     */
    public function getCategories()
    {
        $categories = Category::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return response()->json([
            'success' => true,
            'categories' => $categories
        ]);
    }

    /**
     * Get customer tiers
     */
    public function getCustomerTiers()
    {
        // Define available customer tiers
        $tiers = [
            ['value' => 'bronze', 'label' => 'Bronze'],
            ['value' => 'silver', 'label' => 'Silver'],
            ['value' => 'gold', 'label' => 'Gold'],
            ['value' => 'platinum', 'label' => 'Platinum'],
            ['value' => 'vip', 'label' => 'VIP'],
        ];

        return response()->json([
            'success' => true,
            'tiers' => $tiers
        ]);
    }
}
