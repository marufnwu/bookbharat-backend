<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wishlist;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class WishlistController extends Controller
{
    /**
     * Display user's wishlist.
     */
    public function index(Request $request)
    {
        try {
            $query = Wishlist::with(['product.images', 'productVariant'])
                ->where('user_id', auth()->id());

            // Apply filters
            if ($request->priority) {
                $query->where('priority', $request->priority);
            }

            if ($request->sort) {
                switch ($request->sort) {
                    case 'priority_desc':
                        $query->orderBy('priority', 'desc');
                        break;
                    case 'priority_asc':
                        $query->orderBy('priority', 'asc');
                        break;
                    case 'newest':
                        $query->orderBy('created_at', 'desc');
                        break;
                    case 'oldest':
                        $query->orderBy('created_at', 'asc');
                        break;
                    default:
                        $query->orderBy('created_at', 'desc');
                }
            } else {
                $query->orderBy('created_at', 'desc');
            }

            $perPage = min($request->get('per_page', 15), 50);
            $wishlistItems = $query->paginate($perPage);

            // Transform the data to include product details
            $wishlistItems->getCollection()->transform(function ($item) {
                return [
                    'id' => $item->id,
                    'notes' => $item->notes,
                    'priority' => $item->priority,
                    'priority_label' => $item->priority_label,
                    'created_at' => $item->created_at,
                    'product' => $item->getProductDetails()
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => $wishlistItems
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve wishlist'
            ], 500);
        }
    }

    /**
     * Add item to wishlist.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'product_variant_id' => 'sometimes|nullable|exists:product_variants,id',
            'notes' => 'sometimes|nullable|string|max:500',
            'priority' => 'sometimes|integer|min:1|max:5'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $product = Product::findOrFail($request->product_id);

            // Check if product variant exists for this product
            if ($request->product_variant_id) {
                $variant = ProductVariant::where('id', $request->product_variant_id)
                    ->where('product_id', $request->product_id)
                    ->first();

                if (!$variant) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Product variant not found or does not belong to this product'
                    ], 404);
                }
            }

            // Check if item already exists in wishlist
            $existingItem = Wishlist::where('user_id', auth()->id())
                ->where('product_id', $request->product_id)
                ->where('product_variant_id', $request->product_variant_id)
                ->first();

            if ($existingItem) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This item is already in your wishlist'
                ], 409);
            }

            $wishlistItem = Wishlist::create([
                'user_id' => auth()->id(),
                'product_id' => $request->product_id,
                'product_variant_id' => $request->product_variant_id,
                'notes' => $request->notes,
                'priority' => $request->priority ?? 3 // Default medium priority
            ]);

            // Load relationships
            $wishlistItem->load(['product.images', 'productVariant']);

            return response()->json([
                'status' => 'success',
                'message' => 'Item added to wishlist successfully',
                'data' => [
                    'id' => $wishlistItem->id,
                    'notes' => $wishlistItem->notes,
                    'priority' => $wishlistItem->priority,
                    'priority_label' => $wishlistItem->priority_label,
                    'created_at' => $wishlistItem->created_at,
                    'product' => $wishlistItem->getProductDetails()
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to add item to wishlist'
            ], 500);
        }
    }

    /**
     * Update wishlist item.
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'notes' => 'sometimes|nullable|string|max:500',
            'priority' => 'sometimes|integer|min:1|max:5'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $wishlistItem = Wishlist::where('user_id', auth()->id())
                ->findOrFail($id);

            $wishlistItem->update($request->only(['notes', 'priority']));

            // Load relationships
            $wishlistItem->load(['product.images', 'productVariant']);

            return response()->json([
                'status' => 'success',
                'message' => 'Wishlist item updated successfully',
                'data' => [
                    'id' => $wishlistItem->id,
                    'notes' => $wishlistItem->notes,
                    'priority' => $wishlistItem->priority,
                    'priority_label' => $wishlistItem->priority_label,
                    'updated_at' => $wishlistItem->updated_at,
                    'product' => $wishlistItem->getProductDetails()
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update wishlist item'
            ], 500);
        }
    }

    /**
     * Remove item from wishlist.
     */
    public function destroy($id)
    {
        try {
            $wishlistItem = Wishlist::where('user_id', auth()->id())
                ->findOrFail($id);

            $wishlistItem->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Item removed from wishlist successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to remove item from wishlist'
            ], 500);
        }
    }

    /**
     * Check if product is in wishlist.
     */
    public function check(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'product_variant_id' => 'sometimes|nullable|exists:product_variants,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $isInWishlist = Wishlist::where('user_id', auth()->id())
                ->where('product_id', $request->product_id)
                ->where('product_variant_id', $request->product_variant_id)
                ->exists();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'is_in_wishlist' => $isInWishlist
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to check wishlist status'
            ], 500);
        }
    }

    /**
     * Move all or selected wishlist items to cart.
     */
    public function moveToCart(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'wishlist_ids' => 'sometimes|array',
            'wishlist_ids.*' => 'exists:wishlists,id',
            'move_all' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $query = Wishlist::where('user_id', auth()->id());

            if ($request->boolean('move_all')) {
                // Move all wishlist items
                $wishlistItems = $query->get();
            } else {
                // Move specific items
                $wishlistIds = $request->wishlist_ids ?? [];
                $wishlistItems = $query->whereIn('id', $wishlistIds)->get();
            }

            $movedItems = 0;
            $errors = [];

            foreach ($wishlistItems as $item) {
                try {
                    // Check if product is still available
                    if (!$item->product->is_active || $item->product->stock_quantity <= 0) {
                        $errors[] = "Product '{$item->product->name}' is not available";
                        continue;
                    }

                    // Add to cart (assuming Cart model and service exist)
                    $cartService = app(\App\Services\CartService::class);
                    $cartService->addItem(
                        auth()->id(),
                        $item->product_id,
                        1, // quantity
                        $item->product_variant_id
                    );

                    // Remove from wishlist
                    $item->delete();
                    $movedItems++;

                } catch (\Exception $e) {
                    $errors[] = "Failed to move '{$item->product->name}': " . $e->getMessage();
                }
            }

            DB::commit();

            $message = $movedItems > 0 
                ? "Successfully moved {$movedItems} item(s) to cart"
                : "No items were moved to cart";

            return response()->json([
                'status' => $movedItems > 0 ? 'success' : 'warning',
                'message' => $message,
                'data' => [
                    'moved_count' => $movedItems,
                    'errors' => $errors
                ]
            ], $movedItems > 0 ? 200 : 400);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to move items to cart'
            ], 500);
        }
    }

    /**
     * Clear entire wishlist.
     */
    public function clear()
    {
        try {
            $deletedCount = Wishlist::where('user_id', auth()->id())->delete();

            return response()->json([
                'status' => 'success',
                'message' => "Successfully cleared {$deletedCount} item(s) from wishlist"
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to clear wishlist'
            ], 500);
        }
    }

    /**
     * Get wishlist statistics.
     */
    public function stats()
    {
        try {
            $stats = Wishlist::where('user_id', auth()->id())
                ->selectRaw('
                    COUNT(*) as total_items,
                    AVG(priority) as avg_priority,
                    COUNT(CASE WHEN priority >= 4 THEN 1 END) as high_priority_items
                ')
                ->first();

            $priorityBreakdown = Wishlist::where('user_id', auth()->id())
                ->selectRaw('priority, COUNT(*) as count')
                ->groupBy('priority')
                ->pluck('count', 'priority');

            return response()->json([
                'status' => 'success',
                'data' => [
                    'total_items' => $stats->total_items ?? 0,
                    'average_priority' => round($stats->avg_priority ?? 0, 2),
                    'high_priority_items' => $stats->high_priority_items ?? 0,
                    'priority_breakdown' => $priorityBreakdown
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve wishlist statistics'
            ], 500);
        }
    }
}