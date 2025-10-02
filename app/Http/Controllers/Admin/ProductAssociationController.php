<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductAssociation;
use App\Jobs\GenerateProductAssociations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProductAssociationController extends Controller
{
    /**
     * Get all product associations with filters and pagination
     */
    public function index(Request $request)
    {
        $query = ProductAssociation::with(['product', 'associatedProduct'])
            ->where('association_type', 'bought_together');

        // Filter by product
        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        // Filter by associated product
        if ($request->has('associated_product_id')) {
            $query->where('associated_product_id', $request->associated_product_id);
        }

        // Filter by confidence score
        if ($request->filled('min_confidence')) {
            $query->where('confidence_score', '>=', $request->min_confidence);
        }

        // Filter by frequency
        if ($request->filled('min_frequency')) {
            $query->where('frequency', '>=', $request->min_frequency);
        }

        // Search by product name
        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('sku', 'LIKE', "%{$search}%");
            })->orWhereHas('associatedProduct', function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('sku', 'LIKE', "%{$search}%");
            });
        }

        // Sort
        $sortBy = $request->get('sort_by', 'confidence_score');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 50);
        $associations = $query->paginate($perPage);

        // Add statistics
        $stats = [
            'total_associations' => ProductAssociation::where('association_type', 'bought_together')->count(),
            'high_confidence' => ProductAssociation::where('association_type', 'bought_together')
                ->where('confidence_score', '>=', 0.5)->count(),
            'medium_confidence' => ProductAssociation::where('association_type', 'bought_together')
                ->whereBetween('confidence_score', [0.3, 0.5])->count(),
            'low_confidence' => ProductAssociation::where('association_type', 'bought_together')
                ->where('confidence_score', '<', 0.3)->count(),
            'average_confidence' => ProductAssociation::where('association_type', 'bought_together')
                ->avg('confidence_score'),
            'average_frequency' => ProductAssociation::where('association_type', 'bought_together')
                ->avg('frequency'),
        ];

        return response()->json([
            'success' => true,
            'associations' => $associations,
            'statistics' => $stats
        ]);
    }

    /**
     * Get a single product association
     */
    public function show($id)
    {
        $association = ProductAssociation::with(['product', 'associatedProduct'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'association' => $association
        ]);
    }

    /**
     * Create a new manual product association
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'associated_product_id' => 'required|exists:products,id|different:product_id',
            'frequency' => 'nullable|integer|min:1|max:1000',
            'confidence_score' => 'nullable|numeric|min:0|max:1',
            'create_bidirectional' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Check if association already exists
            $exists = ProductAssociation::where('product_id', $request->product_id)
                ->where('associated_product_id', $request->associated_product_id)
                ->where('association_type', 'bought_together')
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Association already exists'
                ], 400);
            }

            // Create the association
            $association = ProductAssociation::create([
                'product_id' => $request->product_id,
                'associated_product_id' => $request->associated_product_id,
                'frequency' => $request->frequency ?? 1,
                'confidence_score' => $request->confidence_score ?? 0.5,
                'association_type' => 'bought_together',
                'last_purchased_together' => now(),
            ]);

            // Create bidirectional association if requested
            if ($request->get('create_bidirectional', true)) {
                ProductAssociation::create([
                    'product_id' => $request->associated_product_id,
                    'associated_product_id' => $request->product_id,
                    'frequency' => $request->frequency ?? 1,
                    'confidence_score' => $request->confidence_score ?? 0.5,
                    'association_type' => 'bought_together',
                    'last_purchased_together' => now(),
                ]);
            }

            DB::commit();

            // Clear cache
            \Cache::forget("frequently_bought_{$request->product_id}_2");
            \Cache::forget("frequently_bought_{$request->associated_product_id}_2");

            return response()->json([
                'success' => true,
                'message' => 'Association created successfully',
                'association' => $association->load(['product', 'associatedProduct'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Failed to create product association', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create association: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an existing product association
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'frequency' => 'nullable|integer|min:1|max:1000',
            'confidence_score' => 'nullable|numeric|min:0|max:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $association = ProductAssociation::findOrFail($id);

            if ($request->filled('frequency')) {
                $association->frequency = $request->frequency;
            }

            if ($request->filled('confidence_score')) {
                $association->confidence_score = $request->confidence_score;
            }

            $association->save();

            // Clear cache
            \Cache::forget("frequently_bought_{$association->product_id}_2");

            return response()->json([
                'success' => true,
                'message' => 'Association updated successfully',
                'association' => $association->load(['product', 'associatedProduct'])
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to update product association', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update association: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a product association
     */
    public function destroy($id)
    {
        try {
            $association = ProductAssociation::findOrFail($id);
            $productId = $association->product_id;

            $association->delete();

            // Clear cache
            \Cache::forget("frequently_bought_{$productId}_2");

            return response()->json([
                'success' => true,
                'message' => 'Association deleted successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to delete product association', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete association: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk delete associations
     */
    public function bulkDestroy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'ids.*' => 'exists:product_associations,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $count = ProductAssociation::whereIn('id', $request->ids)->delete();

            // Clear all cache
            \Cache::flush();

            return response()->json([
                'success' => true,
                'message' => "{$count} associations deleted successfully"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete associations: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get associations for a specific product
     */
    public function getProductAssociations($productId)
    {
        $product = Product::findOrFail($productId);

        $associations = ProductAssociation::with('associatedProduct')
            ->where('product_id', $productId)
            ->where('association_type', 'bought_together')
            ->orderBy('confidence_score', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'product' => $product,
            'associations' => $associations
        ]);
    }

    /**
     * Trigger manual generation of associations
     */
    public function generateAssociations(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'months' => 'nullable|integer|min:1|max:24',
            'min_orders' => 'nullable|integer|min:1|max:10',
            'async' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $months = $request->get('months', 6);
        $minOrders = $request->get('min_orders', 2);
        $async = $request->get('async', true);

        try {
            if ($async) {
                // Dispatch job to queue
                GenerateProductAssociations::dispatch($months, $minOrders);

                return response()->json([
                    'success' => true,
                    'message' => 'Association generation job queued. This may take a few minutes.',
                    'async' => true
                ]);
            } else {
                // Run synchronously
                $job = new GenerateProductAssociations($months, $minOrders);
                $job->handle();

                $stats = [
                    'total' => ProductAssociation::where('association_type', 'bought_together')->count(),
                    'high_confidence' => ProductAssociation::where('association_type', 'bought_together')
                        ->where('confidence_score', '>=', 0.5)->count(),
                ];

                return response()->json([
                    'success' => true,
                    'message' => 'Associations generated successfully',
                    'statistics' => $stats,
                    'async' => false
                ]);
            }

        } catch (\Exception $e) {
            \Log::error('Failed to generate associations', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate associations: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear all associations
     */
    public function clearAllAssociations()
    {
        try {
            $count = ProductAssociation::where('association_type', 'bought_together')->delete();

            // Clear cache
            \Cache::flush();

            return response()->json([
                'success' => true,
                'message' => "{$count} associations cleared successfully"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear associations: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get association statistics and dashboard data
     */
    public function statistics()
    {
        try {
            $stats = [
                'total_associations' => ProductAssociation::where('association_type', 'bought_together')->count(),
                'high_confidence' => ProductAssociation::where('association_type', 'bought_together')
                    ->where('confidence_score', '>=', 0.5)->count(),
                'medium_confidence' => ProductAssociation::where('association_type', 'bought_together')
                    ->whereBetween('confidence_score', [0.3, 0.5])->count(),
                'low_confidence' => ProductAssociation::where('association_type', 'bought_together')
                    ->where('confidence_score', '<', 0.3)->count(),
                'average_confidence' => round(ProductAssociation::where('association_type', 'bought_together')
                    ->avg('confidence_score'), 4),
                'average_frequency' => round(ProductAssociation::where('association_type', 'bought_together')
                    ->avg('frequency'), 2),
                'products_with_associations' => ProductAssociation::where('association_type', 'bought_together')
                    ->distinct('product_id')->count('product_id'),
                'last_generated' => ProductAssociation::where('association_type', 'bought_together')
                    ->max('updated_at'),
            ];

            // Top associations
            $topAssociations = ProductAssociation::with(['product', 'associatedProduct'])
                ->where('association_type', 'bought_together')
                ->orderBy('confidence_score', 'desc')
                ->orderBy('frequency', 'desc')
                ->limit(10)
                ->get();

            // Products without associations
            $productsWithoutAssociations = Product::where('is_active', true)
                ->whereDoesntHave('productAssociations')
                ->count();

            return response()->json([
                'success' => true,
                'statistics' => $stats,
                'top_associations' => $topAssociations,
                'products_without_associations' => $productsWithoutAssociations
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get statistics: ' . $e->getMessage()
            ], 500);
        }
    }
}
