<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Services\ProductRecommendationService;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    protected $recommendationService;

    public function __construct(ProductRecommendationService $recommendationService)
    {
        $this->recommendationService = $recommendationService;
    }
    /**
     * Display a listing of products
     */
    public function index(Request $request)
    {
        $query = Product::with(['category', 'images', 'reviews'])
            ->active()
            ->inStock();

        // Search functionality
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%')
                  ->orWhere('sku', 'like', '%' . $request->search . '%');
            });
        }

        // Category filter
        if ($request->filled('category_id')) {
            $query->byCategory($request->category_id);
        }

        // Brand filter
        if ($request->filled('brand')) {
            $query->where('brand', $request->brand);
        }

        // Price range filter
        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        // Featured products
        if ($request->filled('featured')) {
            $query->featured();
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        switch ($sortBy) {
            case 'price_low_to_high':
                $query->orderBy('price', 'asc');
                break;
            case 'price_high_to_low':
                $query->orderBy('price', 'desc');
                break;
            case 'name':
                $query->orderBy('name', 'asc');
                break;
            case 'popularity':
                $query->withCount('orderItems')->orderBy('order_items_count', 'desc');
                break;
            case 'rating':
                $query->withAvg('reviews', 'rating')->orderBy('reviews_avg_rating', 'desc');
                break;
            default:
                $query->orderBy($sortBy, $sortOrder);
        }

        // Pagination
        $perPage = min($request->get('per_page', 20), 50);
        $products = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    /**
     * Display the specified product
     */
    public function show($id)
    {
        // Try to find by ID first (if numeric), then by slug
        $query = Product::with([
            'category',
            'images',
            'reviews' => function($query) {
                $query->approved()->with('user:id,name')->latest();
            },
            'activeBundleVariants'
        ])->active();

        if (is_numeric($id)) {
            $product = $query->findOrFail($id);
        } else {
            $product = $query->where('slug', $id)->firstOrFail();
        }

        // Increment view count (you can add a views column to products table)
        // $product->increment('views');

        // Get related products
        $relatedProducts = Product::with(['category', 'images'])
            ->active()
            ->inStock()
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->limit(8)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'product' => $product,
                'related_products' => $relatedProducts
            ]
        ]);
    }

    /**
     * Get featured products
     */
    public function featured(Request $request)
    {
        $limit = min($request->get('limit', 12), 50);

        $products = Product::with(['category', 'images'])
            ->active()
            ->inStock()
            ->featured()
            ->latest()
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    /**
     * Get products by category
     */
    public function byCategory($categoryId, Request $request)
    {
        $category = Category::active()->findOrFail($categoryId);

        $query = Product::with(['category', 'images'])
            ->active()
            ->inStock()
            ->byCategory($categoryId);

        // Apply same filters as index method
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('brand')) {
            $query->where('brand', $request->brand);
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = min($request->get('per_page', 20), 50);
        $products = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'category' => $category,
                'products' => $products
            ]
        ]);
    }

    /**
     * Search products using Laravel Scout
     */
    public function search(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:2'
        ]);

        $query = $request->get('query');
        $perPage = min($request->get('per_page', 20), 50);

        // Use Scout search if configured, otherwise fall back to database search
        try {
            $products = Product::search($query)
                ->where('status', 'active')
                ->paginate($perPage);
        } catch (\Exception $e) {
            // Fallback to database search
            $products = Product::with(['category', 'images'])
                ->active()
                ->where(function($q) use ($query) {
                    $q->where('name', 'like', '%' . $query . '%')
                      ->orWhere('description', 'like', '%' . $query . '%')
                      ->orWhere('sku', 'like', '%' . $query . '%')
                      ->orWhere('brand', 'like', '%' . $query . '%');
                })
                ->paginate($perPage);
        }

        return response()->json([
            'success' => true,
            'data' => $products,
            'query' => $query
        ]);
    }

    /**
     * Get product suggestions for autocomplete
     */
    public function suggestions(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:2'
        ]);

        $query = $request->get('query');
        $limit = min($request->get('limit', 10), 20);

        $suggestions = Product::select('id', 'name', 'slug', 'price')
            ->with('images:id,product_id,image_path,is_primary')
            ->active()
            ->where('name', 'like', '%' . $query . '%')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $suggestions
        ]);
    }

    /**
     * Get product filters/facets
     */
    public function filters(Request $request)
    {
        $categoryId = $request->get('category_id');

        $query = Product::active();

        if ($categoryId) {
            $query->byCategory($categoryId);
        }

        // Get price range
        $priceRange = $query->selectRaw('MIN(price) as min_price, MAX(price) as max_price')->first();

        // Get brands
        $brands = Product::active()
            ->when($categoryId, function($q) use ($categoryId) {
                return $q->byCategory($categoryId);
            })
            ->whereNotNull('brand')
            ->distinct()
            ->pluck('brand')
            ->sort()
            ->values();

        // Get categories (if not filtering by category)
        $categories = null;
        if (!$categoryId) {
            $categories = Category::active()
                ->whereHas('products', function($q) {
                    $q->active();
                })
                ->select('id', 'name', 'slug')
                ->get();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'price_range' => $priceRange,
                'brands' => $brands,
                'categories' => $categories
            ]
        ]);
    }

    /**
     * Get products grouped by categories for homepage
     */
    public function getByCategories(Request $request)
    {
        $limit = min($request->get('limit', 6), 20); // Categories limit
        $productsPerCategory = min($request->get('products_per_category', 4), 12);

        // Get active categories with products
        $categories = Category::active()
            ->whereHas('products', function($query) {
                $query->active()->inStock();
            })
            ->with(['products' => function($query) use ($productsPerCategory) {
                $query->active()
                      ->inStock()
                      ->with(['images', 'category'])
                      ->latest()
                      ->limit($productsPerCategory);
            }])
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    /**
     * Get related products for a specific product
     */
    public function getRelatedProducts($id)
    {
        // Handle both ID and slug
        if (!is_numeric($id)) {
            $product = Product::where('slug', $id)->firstOrFail();
            $id = $product->id; // Use numeric ID for the service
        }

        $relatedProducts = $this->recommendationService->getRelatedProducts($id, 6);

        return response()->json([
            'success' => true,
            'data' => $relatedProducts
        ]);
    }

    /**
     * Get frequently bought together products
     */
    public function getFrequentlyBoughtTogether($id)
    {
        // Handle both ID and slug - load with images relationship
        if (is_numeric($id)) {
            $product = Product::with('images')->findOrFail($id);
        } else {
            $product = Product::with('images')->where('slug', $id)->firstOrFail();
            $id = $product->id; // Use numeric ID for the service
        }

        $result = $this->recommendationService->getFrequentlyBoughtTogether($id, 2);

        // Get primary image (first image from images collection)
        $primaryImage = $product->images->first();
        $imageUrl = $primaryImage ? $primaryImage->image_url : null;

        return response()->json([
            'success' => true,
            'data' => [
                'main_product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => $product->price,
                    'image_url' => $imageUrl
                ],
                'products' => $result['products'],
                'bundle_data' => $result['bundle_data']
            ]
        ]);
    }
}
