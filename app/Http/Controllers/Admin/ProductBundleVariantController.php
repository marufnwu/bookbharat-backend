<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductBundleVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProductBundleVariantController extends Controller
{
    /**
     * Display a listing of bundle variants for a product
     */
    public function index(Product $product)
    {
        $variants = $product->bundleVariants()->orderBy('sort_order')->get();

        return response()->json([
            'success' => true,
            'bundle_variants' => $variants,
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'stock_quantity' => $product->stock_quantity,
            ]
        ]);
    }

    /**
     * Store a newly created bundle variant
     */
    public function store(Request $request, Product $product)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:100|unique:product_bundle_variants,sku',
            'quantity' => 'required|integer|min:2',
            'pricing_type' => 'required|in:percentage_discount,fixed_price,fixed_discount',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'fixed_price' => 'nullable|numeric|min:0',
            'fixed_discount' => 'nullable|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0',
            'stock_management_type' => 'required|in:use_main_product,separate_stock',
            'stock_quantity' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Validate pricing based on pricing_type
        $pricingType = $request->pricing_type;

        if ($pricingType === 'percentage_discount' && !$request->has('discount_percentage')) {
            return response()->json([
                'success' => false,
                'message' => 'Discount percentage is required for percentage_discount pricing type'
            ], 422);
        }

        if ($pricingType === 'fixed_price' && !$request->has('fixed_price')) {
            return response()->json([
                'success' => false,
                'message' => 'Fixed price is required for fixed_price pricing type'
            ], 422);
        }

        if ($pricingType === 'fixed_discount' && !$request->has('fixed_discount')) {
            return response()->json([
                'success' => false,
                'message' => 'Fixed discount is required for fixed_discount pricing type'
            ], 422);
        }

        // Validate stock for separate_stock management
        if ($request->stock_management_type === 'separate_stock' && !$request->has('stock_quantity')) {
            return response()->json([
                'success' => false,
                'message' => 'Stock quantity is required for separate stock management'
            ], 422);
        }

        try {
            $bundleVariant = DB::transaction(function () use ($product, $request) {
                $data = $request->only([
                    'name',
                    'sku',
                    'quantity',
                    'pricing_type',
                    'discount_percentage',
                    'fixed_price',
                    'fixed_discount',
                    'compare_price',
                    'stock_management_type',
                    'stock_quantity',
                    'is_active',
                    'sort_order',
                    'metadata',
                ]);

                $data['product_id'] = $product->id;
                $data['is_active'] = $request->input('is_active', true);
                $data['sort_order'] = $request->input('sort_order', 0);

                return ProductBundleVariant::create($data);
            });

            $bundleVariant->load('product');

            return response()->json([
                'success' => true,
                'message' => 'Bundle variant created successfully',
                'bundle_variant' => $bundleVariant
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create bundle variant',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified bundle variant
     */
    public function show(Product $product, ProductBundleVariant $bundleVariant)
    {
        // Ensure the bundle variant belongs to the product
        if ($bundleVariant->product_id !== $product->id) {
            return response()->json([
                'success' => false,
                'message' => 'Bundle variant does not belong to this product'
            ], 404);
        }

        $bundleVariant->load('product');

        return response()->json([
            'success' => true,
            'bundle_variant' => $bundleVariant
        ]);
    }

    /**
     * Update the specified bundle variant
     */
    public function update(Request $request, Product $product, ProductBundleVariant $bundleVariant)
    {
        // Ensure the bundle variant belongs to the product
        if ($bundleVariant->product_id !== $product->id) {
            return response()->json([
                'success' => false,
                'message' => 'Bundle variant does not belong to this product'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'sku' => 'sometimes|required|string|max:100|unique:product_bundle_variants,sku,' . $bundleVariant->id,
            'quantity' => 'sometimes|required|integer|min:2',
            'pricing_type' => 'sometimes|required|in:percentage_discount,fixed_price,fixed_discount',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'fixed_price' => 'nullable|numeric|min:0',
            'fixed_discount' => 'nullable|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0',
            'stock_management_type' => 'sometimes|required|in:use_main_product,separate_stock',
            'stock_quantity' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::transaction(function () use ($bundleVariant, $request) {
                $bundleVariant->update($request->only([
                    'name',
                    'sku',
                    'quantity',
                    'pricing_type',
                    'discount_percentage',
                    'fixed_price',
                    'fixed_discount',
                    'compare_price',
                    'stock_management_type',
                    'stock_quantity',
                    'is_active',
                    'sort_order',
                    'metadata',
                ]));
            });

            $bundleVariant->load('product');

            return response()->json([
                'success' => true,
                'message' => 'Bundle variant updated successfully',
                'bundle_variant' => $bundleVariant
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update bundle variant',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified bundle variant
     */
    public function destroy(Product $product, ProductBundleVariant $bundleVariant)
    {
        // Ensure the bundle variant belongs to the product
        if ($bundleVariant->product_id !== $product->id) {
            return response()->json([
                'success' => false,
                'message' => 'Bundle variant does not belong to this product'
            ], 404);
        }

        try {
            $bundleVariant->delete();

            return response()->json([
                'success' => true,
                'message' => 'Bundle variant deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete bundle variant',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate price for a bundle variant (preview)
     */
    public function calculatePrice(Request $request, Product $product)
    {
        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer|min:2',
            'pricing_type' => 'required|in:percentage_discount,fixed_price,fixed_discount',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'fixed_price' => 'nullable|numeric|min:0',
            'fixed_discount' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $quantity = $request->quantity;
        $pricingType = $request->pricing_type;
        $originalPrice = $product->price * $quantity;
        $bundlePrice = $originalPrice;

        switch ($pricingType) {
            case 'percentage_discount':
                $discount = $request->input('discount_percentage', 0);
                $bundlePrice = round($originalPrice * (1 - $discount / 100), 2);
                break;

            case 'fixed_price':
                $bundlePrice = $request->input('fixed_price', $originalPrice);
                break;

            case 'fixed_discount':
                $discount = $request->input('fixed_discount', 0);
                $bundlePrice = max(0, round($originalPrice - $discount, 2));
                break;
        }

        $savings = max(0, $originalPrice - $bundlePrice);
        $savingsPercentage = $originalPrice > 0 ? round(($savings / $originalPrice) * 100, 2) : 0;

        return response()->json([
            'success' => true,
            'calculation' => [
                'product_price' => $product->price,
                'quantity' => $quantity,
                'original_price' => $originalPrice,
                'bundle_price' => $bundlePrice,
                'savings_amount' => $savings,
                'savings_percentage' => $savingsPercentage,
                'pricing_type' => $pricingType,
            ]
        ]);
    }

    /**
     * Bulk update sort order
     */
    public function updateSortOrder(Request $request, Product $product)
    {
        $validator = Validator::make($request->all(), [
            'variants' => 'required|array',
            'variants.*.id' => 'required|exists:product_bundle_variants,id',
            'variants.*.sort_order' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::transaction(function () use ($request, $product) {
                foreach ($request->variants as $variantData) {
                    ProductBundleVariant::where('id', $variantData['id'])
                        ->where('product_id', $product->id)
                        ->update(['sort_order' => $variantData['sort_order']]);
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Sort order updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update sort order',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

