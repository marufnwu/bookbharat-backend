<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use App\Models\Product;
use App\Models\Category;
use App\Models\User;

class CacheService
{
    protected $defaultTtl = 3600; // 1 hour
    protected $shortTtl = 300; // 5 minutes
    protected $longTtl = 86400; // 24 hours

    /**
     * Cache product data with multiple layers
     */
    public function cacheProduct(Product $product, $ttl = null)
    {
        $ttl = $ttl ?? $this->defaultTtl;
        $key = "product:{$product->id}";
        
        $productData = [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'price' => $product->price,
            'compare_price' => $product->compare_price,
            'description' => $product->description,
            'short_description' => $product->short_description,
            'brand' => $product->brand,
            'sku' => $product->sku,
            'stock_quantity' => $product->stock_quantity,
            'in_stock' => $product->in_stock,
            'category_id' => $product->category_id,
            'is_featured' => $product->is_featured,
            'average_rating' => $product->average_rating,
            'total_reviews' => $product->total_reviews,
            'primary_image' => $product->primary_image,
            'cached_at' => now()->toISOString(),
        ];

        // Cache the main product data
        Cache::put($key, $productData, $ttl);
        
        // Cache product variants if they exist
        if ($product->variants && $product->variants->count() > 0) {
            $variantsKey = "product:{$product->id}:variants";
            $variantsData = $product->variants->map(function ($variant) {
                return [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'price' => $variant->price,
                    'compare_price' => $variant->compare_price,
                    'stock_quantity' => $variant->stock_quantity,
                    'available_stock' => $variant->available_stock,
                    'is_active' => $variant->is_active,
                    'combination_hash' => $variant->combination_hash,
                    'variant_attributes' => $variant->variant_attributes,
                ];
            });
            
            Cache::put($variantsKey, $variantsData, $ttl);
        }
        
        // Cache related products
        $this->cacheRelatedProducts($product, $ttl);
        
        return $productData;
    }

    /**
     * Get cached product data
     */
    public function getCachedProduct($productId)
    {
        $key = "product:{$productId}";
        return Cache::get($key);
    }

    /**
     * Cache category hierarchy and products
     */
    public function cacheCategory(Category $category, $includeProducts = false, $ttl = null)
    {
        $ttl = $ttl ?? $this->longTtl;
        $key = "category:{$category->id}";
        
        $categoryData = [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'parent_id' => $category->parent_id,
            'image' => $category->image,
            'is_active' => $category->is_active,
            'sort_order' => $category->sort_order,
            'cached_at' => now()->toISOString(),
        ];

        Cache::put($key, $categoryData, $ttl);
        
        // Cache category products if requested
        if ($includeProducts) {
            $this->cacheCategoryProducts($category, $ttl);
        }
        
        return $categoryData;
    }

    /**
     * Cache user-specific data
     */
    public function cacheUserData(User $user, $ttl = null)
    {
        $ttl = $ttl ?? $this->defaultTtl;
        $baseKey = "user:{$user->id}";
        
        // Cache user profile
        $profileData = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'customer_groups' => $user->customerGroups->pluck('name'),
            'analytics' => $user->analytics ? [
                'lifetime_value' => $user->analytics->lifetime_value,
                'customer_segment' => $user->analytics->customer_segment,
                'lifecycle_stage' => $user->analytics->lifecycle_stage,
            ] : null,
        ];
        
        Cache::put("{$baseKey}:profile", $profileData, $ttl);
        
        // Cache user's wishlist
        if ($user->wishlists) {
            $wishlistData = $user->wishlists->pluck('product_id');
            Cache::put("{$baseKey}:wishlist", $wishlistData, $ttl);
        }
        
        // Cache user's recent orders
        $recentOrders = $user->orders()
            ->latest()
            ->limit(5)
            ->get(['id', 'order_number', 'status', 'total_amount', 'created_at']);
        
        Cache::put("{$baseKey}:recent_orders", $recentOrders, $this->shortTtl);
        
        return $profileData;
    }

    /**
     * Cache homepage data
     */
    public function cacheHomepageData($ttl = null)
    {
        $ttl = $ttl ?? $this->defaultTtl;
        
        $data = [
            'featured_products' => Product::featured()->inStock()->limit(12)->get(),
            'trending_products' => $this->getTrendingProducts(12),
            'new_arrivals' => Product::active()->inStock()->latest()->limit(12)->get(),
            'top_categories' => Category::active()->withCount('products')->orderBy('products_count', 'desc')->limit(8)->get(),
            'cached_at' => now()->toISOString(),
        ];
        
        Cache::put('homepage:data', $data, $ttl);
        
        return $data;
    }

    /**
     * Cache search results
     */
    public function cacheSearchResults($query, $filters, $results, $ttl = null)
    {
        $ttl = $ttl ?? $this->shortTtl;
        $key = $this->generateSearchCacheKey($query, $filters);
        
        $cacheData = [
            'query' => $query,
            'filters' => $filters,
            'results' => $results,
            'cached_at' => now()->toISOString(),
        ];
        
        Cache::put($key, $cacheData, $ttl);
        
        return $cacheData;
    }

    /**
     * Get cached search results
     */
    public function getCachedSearchResults($query, $filters)
    {
        $key = $this->generateSearchCacheKey($query, $filters);
        return Cache::get($key);
    }

    /**
     * Cache pricing calculations
     */
    public function cachePricingCalculation($productId, $variantId, $userId, $context, $pricingData, $ttl = null)
    {
        $ttl = $ttl ?? $this->shortTtl;
        $key = $this->generatePricingCacheKey($productId, $variantId, $userId, $context);
        
        Cache::put($key, $pricingData, $ttl);
        
        return $pricingData;
    }

    /**
     * Get cached pricing calculation
     */
    public function getCachedPricingCalculation($productId, $variantId, $userId, $context)
    {
        $key = $this->generatePricingCacheKey($productId, $variantId, $userId, $context);
        return Cache::get($key);
    }

    /**
     * Cache API responses
     */
    public function cacheApiResponse($endpoint, $params, $response, $ttl = null)
    {
        $ttl = $ttl ?? $this->defaultTtl;
        $key = $this->generateApiCacheKey($endpoint, $params);
        
        $cacheData = [
            'endpoint' => $endpoint,
            'params' => $params,
            'response' => $response,
            'cached_at' => now()->toISOString(),
        ];
        
        Cache::put($key, $cacheData, $ttl);
        
        return $cacheData;
    }

    /**
     * Get cached API response
     */
    public function getCachedApiResponse($endpoint, $params)
    {
        $key = $this->generateApiCacheKey($endpoint, $params);
        return Cache::get($key);
    }

    /**
     * Invalidate product-related caches
     */
    public function invalidateProductCaches($productId)
    {
        $patterns = [
            "product:{$productId}",
            "product:{$productId}:*",
            "category:*:products", // Category product lists
            "homepage:data",
            "search:*", // Search results
            "recommendations:*", // Product recommendations
        ];
        
        foreach ($patterns as $pattern) {
            if (str_contains($pattern, '*')) {
                $this->deleteByPattern($pattern);
            } else {
                Cache::forget($pattern);
            }
        }
    }

    /**
     * Invalidate user-related caches
     */
    public function invalidateUserCaches($userId)
    {
        $patterns = [
            "user:{$userId}:*",
            "recommendations:user:{$userId}:*",
            "pricing:*:user:{$userId}:*",
        ];
        
        foreach ($patterns as $pattern) {
            $this->deleteByPattern($pattern);
        }
    }

    /**
     * Invalidate category-related caches
     */
    public function invalidateCategoryCaches($categoryId)
    {
        $patterns = [
            "category:{$categoryId}",
            "category:{$categoryId}:*",
            "homepage:data",
        ];
        
        foreach ($patterns as $pattern) {
            if (str_contains($pattern, '*')) {
                $this->deleteByPattern($pattern);
            } else {
                Cache::forget($pattern);
            }
        }
    }

    /**
     * Warm up cache for critical data
     */
    public function warmUpCache()
    {
        // Warm up homepage data
        $this->cacheHomepageData();
        
        // Warm up top categories
        $topCategories = Category::active()->withCount('products')->orderBy('products_count', 'desc')->limit(20)->get();
        foreach ($topCategories as $category) {
            $this->cacheCategory($category, true);
        }
        
        // Warm up featured products
        $featuredProducts = Product::featured()->inStock()->limit(50)->get();
        foreach ($featuredProducts as $product) {
            $this->cacheProduct($product);
        }
        
        // Warm up trending products
        $trendingProducts = $this->getTrendingProducts(50);
        foreach ($trendingProducts as $product) {
            $this->cacheProduct($product);
        }
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats()
    {
        try {
            $redis = Redis::connection();
            
            $info = $redis->info('memory');
            $keyspace = $redis->info('keyspace');
            
            return [
                'memory_usage' => $info['used_memory_human'] ?? 'N/A',
                'total_keys' => $keyspace['db0']['keys'] ?? 0,
                'hit_rate' => $this->calculateHitRate(),
                'cache_size' => $this->getCacheSize(),
            ];
            
        } catch (\Exception $e) {
            return [
                'error' => 'Could not retrieve cache stats',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Clear all application caches
     */
    public function clearAllCaches()
    {
        try {
            Cache::flush();
            return ['success' => true, 'message' => 'All caches cleared successfully'];
            
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Protected helper methods

    protected function cacheRelatedProducts(Product $product, $ttl)
    {
        $key = "product:{$product->id}:related";
        
        // Get products from same category
        $related = Product::active()
            ->inStock()
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->limit(8)
            ->get(['id', 'name', 'price', 'primary_image']);
            
        Cache::put($key, $related, $ttl);
    }

    protected function cacheCategoryProducts(Category $category, $ttl)
    {
        $key = "category:{$category->id}:products";
        
        $products = $category->products()
            ->active()
            ->inStock()
            ->limit(20)
            ->get(['id', 'name', 'price', 'primary_image', 'average_rating']);
            
        Cache::put($key, $products, $ttl);
    }

    protected function getTrendingProducts($limit)
    {
        return Product::active()
            ->inStock()
            ->withCount(['orderItems' => function ($query) {
                $query->whereHas('order', function ($q) {
                    $q->where('created_at', '>=', now()->subDays(7))
                      ->where('status', 'completed');
                });
            }])
            ->orderBy('order_items_count', 'desc')
            ->limit($limit)
            ->get();
    }

    protected function generateSearchCacheKey($query, $filters)
    {
        $filterString = http_build_query($filters);
        return 'search:' . md5($query . $filterString);
    }

    protected function generatePricingCacheKey($productId, $variantId, $userId, $context)
    {
        $contextString = http_build_query($context);
        return "pricing:{$productId}:" . ($variantId ?? 'null') . ":user:{$userId}:" . md5($contextString);
    }

    protected function generateApiCacheKey($endpoint, $params)
    {
        $paramString = http_build_query($params);
        return 'api:' . md5($endpoint . $paramString);
    }

    protected function deleteByPattern($pattern)
    {
        try {
            $redis = Redis::connection();
            $keys = $redis->keys($pattern);
            
            if (!empty($keys)) {
                $redis->del($keys);
            }
            
        } catch (\Exception $e) {
            // Fallback for non-Redis cache drivers
            // This is less efficient but works with other cache drivers
        }
    }

    protected function calculateHitRate()
    {
        // This would need to be implemented based on your cache driver
        // For Redis, you can use INFO stats
        return 'N/A';
    }

    protected function getCacheSize()
    {
        try {
            $redis = Redis::connection();
            $info = $redis->info('memory');
            return $info['used_memory_human'] ?? 'N/A';
            
        } catch (\Exception $e) {
            return 'N/A';
        }
    }
}