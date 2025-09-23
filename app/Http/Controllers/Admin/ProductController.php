<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\ProductAttribute;
use App\Models\ProductVariant;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    protected $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
        // Middleware is already handled in routes
        // $this->middleware('permission:manage-products');
    }

    public function index(Request $request)
    {
        $query = Product::with(['category', 'variants'])
            ->withCount(['orderItems', 'reviews', 'variants']);

        // Filters
        if ($request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->status) {
            match($request->status) {
                'active' => $query->where('is_active', true),
                'inactive' => $query->where('is_active', false),
                'in_stock' => $query->where('stock_quantity', '>', 0),
                'out_of_stock' => $query->where('stock_quantity', '<=', 0),
                'low_stock' => $query->where('stock_quantity', '>', 0)->where('stock_quantity', '<=', 10),
                'featured' => $query->where('is_featured', true),
                default => null
            };
        }

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('sku', 'like', '%' . $request->search . '%')
                  ->orWhere('author', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->price_min || $request->price_max) {
            $query->whereBetween('price', [
                $request->input('price_min', 0),
                $request->input('price_max', PHP_FLOAT_MAX)
            ]);
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $products = $query->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'products' => $products,
            'filters' => $this->getProductFilters(),
            'stats' => $this->getProductStats()
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:products,slug',
            'sku' => 'required|string|max:100|unique:products,sku',
            'category_id' => 'required|exists:categories,id',
            'description' => 'required|string',
            'short_description' => 'nullable|string|max:500',
            'author' => 'nullable|string|max:255',
            'publisher' => 'nullable|string|max:255',
            'isbn' => 'nullable|string|max:20',
            'publication_date' => 'nullable|date',
            'language' => 'nullable|string|max:50',
            'pages' => 'nullable|integer|min:1',
            'weight' => 'nullable|numeric|min:0',
            'dimensions' => 'nullable|array',
            'price' => 'required|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0|gt:price',
            'cost_price' => 'nullable|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'track_stock' => 'nullable|boolean',
            'allow_backorder' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'meta_keywords' => 'nullable|array',
            'images' => 'nullable|array',
            'images.*' => 'string',
            'tags' => 'nullable|array',
            'attributes' => 'nullable|array',
            'variants' => 'nullable|array',
        ]);

        try {
            DB::beginTransaction();

            // Generate slug if not provided
            if (!$request->slug) {
                $slug = Str::slug($request->name);
                $baseSlug = $slug;
                $counter = 1;
                
                while (Product::where('slug', $slug)->exists()) {
                    $slug = $baseSlug . '-' . $counter++;
                }
                $request->merge(['slug' => $slug]);
            }

            $product = Product::create($request->only([
                'name', 'slug', 'sku', 'category_id', 'description', 'short_description',
                'author', 'publisher', 'isbn', 'publication_date', 'language', 'pages',
                'weight', 'dimensions', 'price', 'compare_price', 'cost_price',
                'stock_quantity', 'low_stock_threshold', 'track_stock', 'allow_backorder',
                'is_active', 'is_featured', 'meta_title', 'meta_description', 'meta_keywords',
                'images', 'tags'
            ]));

            // Handle product attributes
            if ($request->attributes) {
                $this->syncProductAttributes($product, $request->attributes);
            }

            // Handle product variants
            if ($request->variants) {
                $this->createProductVariants($product, $request->variants);
            }

            // Update inventory
            $this->inventoryService->updateProductInventory($product->id, [
                'quantity' => $request->stock_quantity,
                'location_id' => 1, // Default warehouse
                'adjustment_type' => 'initial_stock',
                'notes' => 'Initial product creation'
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Product created successfully',
                'product' => $product->load(['category', 'variants'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create product: ' . $e->getMessage()
            ], 400);
        }
    }

    public function show(Product $product)
    {
        $product->load([
            'category',
            'variants.inventoryLevels',
            'reviews.user:id,name',
            'orderItems.order:id,order_number,status'
        ]);

        $analytics = [
            'sales_data' => $this->getProductSalesData($product),
            'inventory_levels' => $this->getProductInventoryLevels($product),
            'performance_metrics' => $this->getProductPerformanceMetrics($product),
            'customer_reviews' => $this->getProductReviewSummary($product),
        ];

        return response()->json([
            'success' => true,
            'product' => $product,
            'analytics' => $analytics
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:products,slug,' . $product->id,
            'sku' => 'required|string|max:100|unique:products,sku,' . $product->id,
            'category_id' => 'required|exists:categories,id',
            'description' => 'required|string',
            'short_description' => 'nullable|string|max:500',
            'author' => 'nullable|string|max:255',
            'publisher' => 'nullable|string|max:255',
            'isbn' => 'nullable|string|max:20',
            'publication_date' => 'nullable|date',
            'language' => 'nullable|string|max:50',
            'pages' => 'nullable|integer|min:1',
            'weight' => 'nullable|numeric|min:0',
            'dimensions' => 'nullable|array',
            'price' => 'required|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'track_stock' => 'nullable|boolean',
            'allow_backorder' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'meta_keywords' => 'nullable|array',
            'images' => 'nullable|array',
            'tags' => 'nullable|array',
            'attributes' => 'nullable|array',
        ]);

        try {
            DB::beginTransaction();

            $oldStockQuantity = $product->stock_quantity;

            $product->update($request->only([
                'name', 'slug', 'sku', 'category_id', 'description', 'short_description',
                'author', 'publisher', 'isbn', 'publication_date', 'language', 'pages',
                'weight', 'dimensions', 'price', 'compare_price', 'cost_price',
                'stock_quantity', 'low_stock_threshold', 'track_stock', 'allow_backorder',
                'is_active', 'is_featured', 'meta_title', 'meta_description', 'meta_keywords',
                'images', 'tags'
            ]));

            // Handle product attributes
            if ($request->has('attributes')) {
                $this->syncProductAttributes($product, $request->attributes);
            }

            // Update inventory if stock quantity changed
            if ($oldStockQuantity !== $request->stock_quantity) {
                $adjustment = $request->stock_quantity - $oldStockQuantity;
                $this->inventoryService->adjustInventory($product->id, [
                    'adjustment' => $adjustment,
                    'adjustment_type' => $adjustment > 0 ? 'restock' : 'adjustment',
                    'notes' => 'Stock updated via admin panel'
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Product updated successfully',
                'product' => $product->load(['category', 'variants'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update product: ' . $e->getMessage()
            ], 400);
        }
    }

    public function destroy(Product $product)
    {
        // Check if product has orders
        if ($product->orderItems()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete product with existing orders'
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Delete related data
            $product->variants()->delete();
            // $product->productAttributes()->delete();
            $product->reviews()->delete();
            $product->wishlists()->delete();

            // Delete the product
            $product->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Product deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete product: ' . $e->getMessage()
            ], 400);
        }
    }

    public function bulkAction(Request $request)
    {
        $request->validate([
            'action' => 'required|in:activate,deactivate,feature,unfeature,delete,update_category,update_price',
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'exists:products,id',
            'category_id' => 'required_if:action,update_category|exists:categories,id',
            'price_adjustment' => 'required_if:action,update_price|numeric',
            'price_adjustment_type' => 'required_if:action,update_price|in:percentage,fixed',
        ]);

        $products = Product::whereIn('id', $request->product_ids);
        $count = 0;

        try {
            DB::beginTransaction();

            switch ($request->action) {
                case 'activate':
                    $count = $products->update(['is_active' => true]);
                    break;

                case 'deactivate':
                    $count = $products->update(['is_active' => false]);
                    break;

                case 'feature':
                    $count = $products->update(['is_featured' => true]);
                    break;

                case 'unfeature':
                    $count = $products->update(['is_featured' => false]);
                    break;

                case 'delete':
                    $deletableProducts = $products->whereDoesntHave('orderItems')->get();
                    foreach ($deletableProducts as $product) {
                        $product->variants()->delete();
                        // $product->productAttributes()->delete();
                        $product->reviews()->delete();
                        $product->wishlists()->delete();
                    }
                    $count = Product::whereIn('id', $deletableProducts->pluck('id'))->delete();
                    break;

                case 'update_category':
                    $count = $products->update(['category_id' => $request->category_id]);
                    break;

                case 'update_price':
                    foreach ($products->get() as $product) {
                        $newPrice = $request->price_adjustment_type === 'percentage' 
                            ? $product->price * (1 + $request->price_adjustment / 100)
                            : $product->price + $request->price_adjustment;
                        
                        $product->update(['price' => max(0, $newPrice)]);
                        $count++;
                    }
                    break;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Bulk {$request->action} completed for {$count} products"
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Bulk action failed: ' . $e->getMessage()
            ], 400);
        }
    }

    public function duplicate(Product $product)
    {
        try {
            DB::beginTransaction();

            $newProduct = $product->replicate();
            $newProduct->name = $product->name . ' (Copy)';
            $newProduct->slug = $product->slug . '-copy-' . time();
            $newProduct->sku = $product->sku . '-COPY';
            $newProduct->is_featured = false;
            $newProduct->stock_quantity = 0;
            $newProduct->save();

            // Duplicate variants
            foreach ($product->variants as $variant) {
                $newVariant = $variant->replicate();
                $newVariant->product_id = $newProduct->id;
                $newVariant->sku = $variant->sku . '-COPY';
                $newVariant->stock_quantity = 0;
                $newVariant->save();
            }

            // Duplicate attributes - disabled for now as productAttributes table doesn't exist
            // foreach ($product->productAttributes as $attribute) {
            //     $newProduct->productAttributes()->create([
            //         'attribute_id' => $attribute->attribute_id,
            //         'value' => $attribute->value,
            //     ]);
            // }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Product duplicated successfully',
                'product' => $newProduct->load(['category', 'variants', 'productAttributes'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to duplicate product: ' . $e->getMessage()
            ], 400);
        }
    }

    protected function getProductFilters(): array
    {
        return [
            'categories' => Category::select('id', 'name')->get(),
            'statuses' => [
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'inactive', 'label' => 'Inactive'],
                ['value' => 'in_stock', 'label' => 'In Stock'],
                ['value' => 'out_of_stock', 'label' => 'Out of Stock'],
                ['value' => 'low_stock', 'label' => 'Low Stock'],
                ['value' => 'featured', 'label' => 'Featured'],
            ],
            'price_ranges' => [
                ['min' => 0, 'max' => 500, 'label' => 'Under ₹500'],
                ['min' => 500, 'max' => 1000, 'label' => '₹500 - ₹1000'],
                ['min' => 1000, 'max' => 2000, 'label' => '₹1000 - ₹2000'],
                ['min' => 2000, 'max' => null, 'label' => 'Over ₹2000'],
            ]
        ];
    }

    protected function getProductStats(): array
    {
        return [
            'total_products' => Product::count(),
            'active_products' => Product::where('is_active', true)->count(),
            'featured_products' => Product::where('is_featured', true)->count(),
            'out_of_stock' => Product::where('stock_quantity', '<=', 0)->count(),
            'low_stock' => Product::where('stock_quantity', '>', 0)->where('stock_quantity', '<=', 10)->count(),
            'average_price' => Product::avg('price'),
        ];
    }

    protected function syncProductAttributes(Product $product, array $attributes): void
    {
        // Disabled for now as productAttributes table doesn't exist
        // $product->productAttributes()->delete();

        // foreach ($attributes as $attributeData) {
        //     $product->productAttributes()->create([
        //         'attribute_id' => $attributeData['attribute_id'],
        //         'value' => $attributeData['value'],
        //     ]);
        // }
    }

    protected function createProductVariants(Product $product, array $variants): void
    {
        foreach ($variants as $variantData) {
            $variant = $product->variants()->create([
                'sku' => $variantData['sku'],
                'price' => $variantData['price'] ?? $product->price,
                'compare_price' => $variantData['compare_price'] ?? null,
                'cost_price' => $variantData['cost_price'] ?? null,
                'stock_quantity' => $variantData['stock_quantity'] ?? 0,
                'low_stock_threshold' => $variantData['low_stock_threshold'] ?? 5,
                'track_stock' => $variantData['track_stock'] ?? true,
                'is_active' => $variantData['is_active'] ?? true,
                'variant_attributes' => $variantData['attributes'] ?? [],
                'combination_hash' => md5(json_encode($variantData['attributes'] ?? [])),
            ]);
        }
    }

    protected function getProductSalesData(Product $product): array
    {
        return [
            'total_sold' => $product->orderItems()->whereHas('order', function ($query) {
                $query->where('status', 'delivered');
            })->sum('quantity'),
            'total_revenue' => $product->orderItems()->whereHas('order', function ($query) {
                $query->where('status', 'delivered');
            })->sum('total_price'),
            'monthly_sales' => $product->orderItems()
                ->whereHas('order', function ($query) {
                    $query->where('status', 'delivered')
                          ->where('created_at', '>=', now()->subMonths(12));
                })
                ->selectRaw("YEAR(created_at) as year, MONTH(created_at) as month, COUNT(*) as count, SUM(total_price) as revenue")
                ->groupBy('year', 'month')
                ->orderBy('year', 'desc')
                ->orderBy('month', 'desc')
                ->get(),
        ];
    }

    protected function getProductInventoryLevels(Product $product): array
    {
        return [
            'current_stock' => $product->stock_quantity,
            'reserved_stock' => 0, // $product->reservations()->sum('quantity'), - reservations table doesn't exist
            'available_stock' => $product->available_stock,
            'reorder_point' => $product->low_stock_threshold,
            'stock_status' => $product->stock_status,
        ];
    }

    protected function getProductPerformanceMetrics(Product $product): array
    {
        $views = $product->views ?? 0;
        $orders = $product->orderItems()->whereHas('order', function ($query) {
            $query->where('status', '!=', 'cancelled');
        })->count();

        return [
            'view_count' => $views,
            'conversion_rate' => $views > 0 ? ($orders / $views) * 100 : 0,
            'average_rating' => $product->average_rating,
            'total_reviews' => $product->total_reviews,
            'wishlist_count' => $product->wishlists()->count(),
            'return_rate' => $this->calculateReturnRate($product),
        ];
    }

    protected function getProductReviewSummary(Product $product): array
    {
        $reviews = $product->reviews();

        return [
            'total_reviews' => $reviews->count(),
            'average_rating' => $reviews->avg('rating'),
            'rating_distribution' => $reviews->selectRaw('rating, COUNT(*) as count')
                ->groupBy('rating')
                ->orderBy('rating', 'desc')
                ->pluck('count', 'rating'),
            'recent_reviews' => $reviews->with('user:id,name')
                ->latest()
                ->limit(5)
                ->get(),
        ];
    }

    protected function calculateReturnRate(Product $product): float
    {
        $totalOrders = $product->orderItems()->whereHas('order', function ($query) {
            $query->where('status', 'delivered');
        })->count();

        if ($totalOrders === 0) {
            return 0;
        }

        $returns = $product->returns()->where('status', 'approved')->count();

        return ($returns / $totalOrders) * 100;
    }

    /**
     * Get product analytics
     */
    public function analytics(Request $request, Product $product)
    {
        $analytics = [
            'sales_data' => $this->getProductSalesData($product),
            'inventory_levels' => $this->getProductInventoryLevels($product),
            'performance_metrics' => $this->getProductPerformanceMetrics($product),
            'customer_reviews' => $this->getProductReviewSummary($product),
        ];

        return response()->json([
            'success' => true,
            'product' => $product->only(['id', 'name', 'sku']),
            'analytics' => $analytics
        ]);
    }
}