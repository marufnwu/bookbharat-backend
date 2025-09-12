<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductAssociation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ProductRecommendationService
{
    /**
     * Get related products for a given product
     */
    public function getRelatedProducts($productId, $limit = 6)
    {
        $cacheKey = "related_products_{$productId}_{$limit}";
        
        return Cache::remember($cacheKey, 3600, function () use ($productId, $limit) {
            $product = Product::find($productId);
            
            if (!$product) {
                return collect();
            }

            // First, check for manual overrides
            $overrides = DB::table('related_products_overrides')
                ->where('product_id', $productId)
                ->where('is_active', true)
                ->orderBy('priority', 'desc')
                ->pluck('related_product_id')
                ->toArray();

            $relatedProducts = collect();

            // Add manually curated products
            if (!empty($overrides)) {
                $manualProducts = Product::whereIn('id', $overrides)
                    ->where('status', 'active')
                    ->with(['images', 'category'])
                    ->get();
                $relatedProducts = $relatedProducts->concat($manualProducts);
            }

            // If we need more products, add from same category
            if ($relatedProducts->count() < $limit) {
                $remainingLimit = $limit - $relatedProducts->count();
                $excludeIds = $relatedProducts->pluck('id')->push($productId)->toArray();

                // Get products from the same category
                $categoryProducts = Product::where('category_id', $product->category_id)
                    ->whereNotIn('id', $excludeIds)
                    ->where('status', 'active')
                    ->with(['images', 'category'])
                    ->orderBy('rating', 'desc')
                    ->orderBy('sales_count', 'desc')
                    ->limit($remainingLimit)
                    ->get();

                $relatedProducts = $relatedProducts->concat($categoryProducts);
            }

            // If still need more, add bestsellers
            if ($relatedProducts->count() < $limit) {
                $remainingLimit = $limit - $relatedProducts->count();
                $excludeIds = $relatedProducts->pluck('id')->push($productId)->toArray();

                $bestsellers = Product::whereNotIn('id', $excludeIds)
                    ->where('status', 'active')
                    ->where('is_bestseller', true)
                    ->with(['images', 'category'])
                    ->orderBy('rating', 'desc')
                    ->limit($remainingLimit)
                    ->get();

                $relatedProducts = $relatedProducts->concat($bestsellers);
            }

            return $relatedProducts->take($limit);
        });
    }

    /**
     * Get frequently bought together products
     */
    public function getFrequentlyBoughtTogether($productId, $limit = 2)
    {
        $cacheKey = "frequently_bought_{$productId}_{$limit}";
        
        return Cache::remember($cacheKey, 3600, function () use ($productId, $limit) {
            $product = Product::find($productId);
            
            if (!$product) {
                return [
                    'products' => collect(),
                    'bundle_data' => null
                ];
            }

            // Get products frequently bought together from associations
            $associations = ProductAssociation::where('product_id', $productId)
                ->where('association_type', 'bought_together')
                ->where('confidence_score', '>=', 0.3)
                ->orderBy('confidence_score', 'desc')
                ->orderBy('frequency', 'desc')
                ->limit($limit * 2) // Get extra to filter
                ->with('associatedProduct')
                ->get();

            $frequentlyBought = collect();

            // Filter out inactive or out-of-stock products
            foreach ($associations as $association) {
                if ($association->associatedProduct && 
                    $association->associatedProduct->status === 'active' &&
                    $association->associatedProduct->in_stock) {
                    
                    $associatedProduct = $association->associatedProduct;
                    $associatedProduct->association_frequency = $association->frequency;
                    $associatedProduct->confidence_score = $association->confidence_score;
                    
                    $frequentlyBought->push($associatedProduct);
                    
                    if ($frequentlyBought->count() >= $limit) {
                        break;
                    }
                }
            }

            // If not enough associated products, add from same author/brand
            if ($frequentlyBought->count() < $limit) {
                $remainingLimit = $limit - $frequentlyBought->count();
                $excludeIds = $frequentlyBought->pluck('id')->push($productId)->toArray();

                $similarProducts = Product::where(function ($query) use ($product) {
                        if ($product->brand) {
                            $query->where('brand', $product->brand);
                        }
                        if ($product->author) {
                            $query->orWhere('author', $product->author);
                        }
                    })
                    ->whereNotIn('id', $excludeIds)
                    ->where('status', 'active')
                    ->where('in_stock', true)
                    ->with(['images', 'category'])
                    ->orderBy('rating', 'desc')
                    ->limit($remainingLimit)
                    ->get();

                $frequentlyBought = $frequentlyBought->concat($similarProducts);
            }

            // If still not enough, add bestsellers from same category
            if ($frequentlyBought->count() < $limit) {
                $remainingLimit = $limit - $frequentlyBought->count();
                $excludeIds = $frequentlyBought->pluck('id')->push($productId)->toArray();

                $categoryBestsellers = Product::where('category_id', $product->category_id)
                    ->whereNotIn('id', $excludeIds)
                    ->where('status', 'active')
                    ->where('in_stock', true)
                    ->where('is_bestseller', true)
                    ->with(['images', 'category'])
                    ->orderBy('rating', 'desc')
                    ->limit($remainingLimit)
                    ->get();

                $frequentlyBought = $frequentlyBought->concat($categoryBestsellers);
            }

            // Calculate bundle pricing
            $bundleData = $this->calculateBundlePrice($product, $frequentlyBought);

            return [
                'products' => $frequentlyBought->take($limit),
                'bundle_data' => $bundleData
            ];
        });
    }

    /**
     * Calculate bundle price and savings
     */
    protected function calculateBundlePrice($mainProduct, $additionalProducts)
    {
        $products = collect([$mainProduct])->concat($additionalProducts);
        
        $totalPrice = $products->sum('price');
        $totalOriginalPrice = $products->sum(function ($product) {
            return $product->compare_price ?: $product->price;
        });

        // Dynamic discount based on bundle size
        $discountPercentage = $this->getBundleDiscount($products->count());
        $bundlePrice = $totalPrice * (1 - $discountPercentage / 100);
        $savings = $totalPrice - $bundlePrice;

        // Track bundle view for analytics
        $this->trackBundleView($products->pluck('id')->toArray());

        return [
            'bundle_price' => round($bundlePrice, 2),
            'total_price' => round($totalPrice, 2),
            'total_original_price' => round($totalOriginalPrice, 2),
            'savings' => round($savings, 2),
            'discount_percentage' => $discountPercentage,
            'product_count' => $products->count()
        ];
    }

    /**
     * Get bundle discount based on number of products
     */
    protected function getBundleDiscount($productCount)
    {
        switch ($productCount) {
            case 2:
                return 5; // 5% off for 2 products
            case 3:
                return 10; // 10% off for 3 products
            case 4:
                return 12; // 12% off for 4 products
            default:
                return 15; // 15% off for 5+ products
        }
    }

    /**
     * Track bundle view for analytics
     */
    protected function trackBundleView($productIds)
    {
        sort($productIds);
        $bundleId = 'bundle_' . implode('_', $productIds);

        DB::table('bundle_analytics')
            ->where('bundle_id', $bundleId)
            ->increment('views');
    }

    /**
     * Track when bundle is added to cart
     */
    public function trackBundleAddToCart($productIds)
    {
        sort($productIds);
        $bundleId = 'bundle_' . implode('_', $productIds);

        DB::table('bundle_analytics')
            ->where('bundle_id', $bundleId)
            ->increment('add_to_cart');
    }

    /**
     * Get bundle analytics
     */
    public function getBundleAnalytics($limit = 10)
    {
        return DB::table('bundle_analytics')
            ->orderBy('conversion_rate', 'desc')
            ->orderBy('purchases', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($bundle) {
                $bundle->product_ids = json_decode($bundle->product_ids);
                $bundle->products = Product::whereIn('id', $bundle->product_ids)->get();
                return $bundle;
            });
    }
}