<?php

namespace App\Services;

use App\Models\User;
use App\Models\Product;
use App\Models\ProductRecommendation;
use App\Models\UserBehavior;
use App\Models\Order;
use Illuminate\Support\Collection;

class RecommendationEngine
{
    public function generateRecommendations(User $user, string $context = 'general', int $limit = 10)
    {
        $recommendations = collect();
        
        // 1. Collaborative Filtering
        $collaborativeRecs = $this->getCollaborativeFilteringRecommendations($user, $limit);
        $recommendations = $recommendations->merge($collaborativeRecs);
        
        // 2. Content-Based Filtering
        $contentRecs = $this->getContentBasedRecommendations($user, $limit);
        $recommendations = $recommendations->merge($contentRecs);
        
        // 3. Trending Products
        $trendingRecs = $this->getTrendingRecommendations($user, $limit);
        $recommendations = $recommendations->merge($trendingRecs);
        
        // 4. Recently Viewed Products
        $recentlyViewedRecs = $this->getRecentlyViewedRecommendations($user, $limit);
        $recommendations = $recommendations->merge($recentlyViewedRecs);
        
        // 5. Category-based recommendations
        $categoryRecs = $this->getCategoryBasedRecommendations($user, $limit);
        $recommendations = $recommendations->merge($categoryRecs);
        
        // Combine and rank all recommendations
        $finalRecs = $this->combineAndRankRecommendations($recommendations, $user, $context);
        
        // Store recommendations for tracking
        $this->storeRecommendations($user, $finalRecs->take($limit), $context);
        
        return $finalRecs->take($limit);
    }
    
    protected function getCollaborativeFilteringRecommendations(User $user, int $limit)
    {
        // Find users with similar purchase behavior
        $userOrderedProducts = $user->orders()
            ->where('status', 'completed')
            ->with('items')
            ->get()
            ->pluck('items')
            ->flatten()
            ->pluck('product_id')
            ->unique();
            
        if ($userOrderedProducts->isEmpty()) {
            return collect();
        }
        
        // Find similar users who bought the same products
        $similarUsers = Order::where('status', 'completed')
            ->whereHas('items', function ($query) use ($userOrderedProducts) {
                $query->whereIn('product_id', $userOrderedProducts);
            })
            ->where('user_id', '!=', $user->id)
            ->with('items.product')
            ->get()
            ->groupBy('user_id')
            ->map(function ($orders) use ($userOrderedProducts) {
                $userProducts = $orders->pluck('items')->flatten()->pluck('product_id')->unique();
                $similarity = $userProducts->intersect($userOrderedProducts)->count() / 
                             $userProducts->union($userOrderedProducts)->count();
                return $similarity;
            })
            ->sortDesc()
            ->take(50);
        
        // Get products purchased by similar users
        $recommendedProducts = collect();
        foreach ($similarUsers->keys() as $similarUserId) {
            $products = Order::where('user_id', $similarUserId)
                ->where('status', 'completed')
                ->with('items.product')
                ->get()
                ->pluck('items')
                ->flatten()
                ->pluck('product')
                ->whereNotIn('id', $userOrderedProducts)
                ->take(5);
                
            foreach ($products as $product) {
                $recommendedProducts->push([
                    'product' => $product,
                    'score' => $similarUsers[$similarUserId] * 0.8,
                    'type' => 'collaborative',
                    'reasoning' => 'Customers with similar taste also bought this'
                ]);
            }
        }
        
        return $recommendedProducts->sortByDesc('score')->take($limit);
    }
    
    protected function getContentBasedRecommendations(User $user, int $limit)
    {
        $recommendations = collect();
        
        // Get user's preferred categories and brands
        $analytics = $user->analytics;
        if (!$analytics) {
            return $recommendations;
        }
        
        $preferredCategory = $analytics->favorite_category;
        $preferredBrand = $analytics->favorite_brand;
        
        // Find similar products based on attributes
        $query = Product::active()->inStock();
        
        if ($preferredCategory) {
            $categoryProducts = $query->where('category_id', $preferredCategory)
                ->whereNotIn('id', $this->getUserPurchasedProducts($user))
                ->limit($limit)
                ->get();
                
            foreach ($categoryProducts as $product) {
                $recommendations->push([
                    'product' => $product,
                    'score' => 0.7,
                    'type' => 'content_category',
                    'reasoning' => 'Based on your interest in ' . $product->category->name
                ]);
            }
        }
        
        if ($preferredBrand) {
            $brandProducts = $query->where('brand', $preferredBrand)
                ->whereNotIn('id', $this->getUserPurchasedProducts($user))
                ->limit($limit)
                ->get();
                
            foreach ($brandProducts as $product) {
                $recommendations->push([
                    'product' => $product,
                    'score' => 0.6,
                    'type' => 'content_brand',
                    'reasoning' => 'Based on your preference for ' . $product->brand
                ]);
            }
        }
        
        return $recommendations->sortByDesc('score')->take($limit);
    }
    
    protected function getTrendingRecommendations(User $user, int $limit)
    {
        // Get trending products based on recent sales
        $trendingProducts = Product::active()
            ->inStock()
            ->withCount(['orderItems' => function ($query) {
                $query->whereHas('order', function ($q) {
                    $q->where('created_at', '>=', now()->subDays(7))
                      ->where('status', 'completed');
                });
            }])
            ->whereNotIn('id', $this->getUserPurchasedProducts($user))
            ->orderBy('order_items_count', 'desc')
            ->limit($limit)
            ->get();
            
        return $trendingProducts->map(function ($product) {
            return [
                'product' => $product,
                'score' => 0.5,
                'type' => 'trending',
                'reasoning' => 'Trending product this week'
            ];
        });
    }
    
    protected function getRecentlyViewedRecommendations(User $user, int $limit)
    {
        $recentlyViewed = $user->behavior()
            ->where('action', 'view')
            ->where('created_at', '>=', now()->subDays(7))
            ->with('product')
            ->orderBy('created_at', 'desc')
            ->limit($limit * 2)
            ->get();
            
        $recommendations = collect();
        
        foreach ($recentlyViewed as $behavior) {
            if (!$behavior->product) continue;
            
            // Find related products in the same category
            $relatedProducts = Product::active()
                ->inStock()
                ->where('category_id', $behavior->product->category_id)
                ->where('id', '!=', $behavior->product->id)
                ->whereNotIn('id', $this->getUserPurchasedProducts($user))
                ->limit(2)
                ->get();
                
            foreach ($relatedProducts as $product) {
                $recommendations->push([
                    'product' => $product,
                    'score' => 0.4,
                    'type' => 'recently_viewed',
                    'reasoning' => 'Based on your recent viewing of ' . $behavior->product->name
                ]);
            }
        }
        
        return $recommendations->take($limit);
    }
    
    protected function getCategoryBasedRecommendations(User $user, int $limit)
    {
        // Get best sellers from user's preferred categories
        $analytics = $user->analytics;
        if (!$analytics || !$analytics->favorite_category) {
            return collect();
        }
        
        $bestSellers = Product::active()
            ->inStock()
            ->where('category_id', $analytics->favorite_category)
            ->whereNotIn('id', $this->getUserPurchasedProducts($user))
            ->withCount(['orderItems' => function ($query) {
                $query->whereHas('order', function ($q) {
                    $q->where('status', 'completed');
                });
            }])
            ->orderBy('order_items_count', 'desc')
            ->limit($limit)
            ->get();
            
        return $bestSellers->map(function ($product) {
            return [
                'product' => $product,
                'score' => 0.3,
                'type' => 'category_bestseller',
                'reasoning' => 'Best seller in your preferred category'
            ];
        });
    }
    
    protected function combineAndRankRecommendations(Collection $recommendations, User $user, string $context)
    {
        // Group by product and combine scores
        $grouped = $recommendations->groupBy('product.id')->map(function ($group) {
            $product = $group->first()['product'];
            $totalScore = $group->sum('score');
            $types = $group->pluck('type')->unique()->implode(', ');
            $reasoning = $group->pluck('reasoning')->first();
            
            return [
                'product' => $product,
                'score' => $totalScore,
                'types' => $types,
                'reasoning' => $reasoning
            ];
        });
        
        // Apply context-specific boosting
        $grouped = $this->applyContextBoosting($grouped, $user, $context);
        
        return $grouped->sortByDesc('score')->values();
    }
    
    protected function applyContextBoosting(Collection $recommendations, User $user, string $context)
    {
        return $recommendations->map(function ($rec) use ($user, $context) {
            $product = $rec['product'];
            $score = $rec['score'];
            
            // Boost based on context
            switch ($context) {
                case 'homepage':
                    if ($product->is_featured) $score *= 1.2;
                    break;
                    
                case 'cart':
                    // Boost complementary products
                    $score *= 1.1;
                    break;
                    
                case 'product_page':
                    // Boost related products
                    $score *= 1.15;
                    break;
                    
                case 'checkout':
                    // Boost impulse buy items
                    if ($product->price < 500) $score *= 1.3;
                    break;
            }
            
            // Boost based on user preferences
            $analytics = $user->analytics;
            if ($analytics) {
                if ($product->category_id === $analytics->favorite_category) {
                    $score *= 1.1;
                }
                
                if ($product->brand === $analytics->favorite_brand) {
                    $score *= 1.05;
                }
            }
            
            $rec['score'] = $score;
            return $rec;
        });
    }
    
    protected function storeRecommendations(User $user, Collection $recommendations, string $context)
    {
        foreach ($recommendations as $rec) {
            ProductRecommendation::updateOrCreate([
                'user_id' => $user->id,
                'product_id' => $rec['product']->id,
                'recommendation_type' => $rec['types'] ?? 'hybrid',
            ], [
                'confidence_score' => min(1, $rec['score']),
                'algorithm_used' => 'hybrid',
                'reasoning' => $rec['reasoning'] ?? null,
                'is_active' => true,
            ]);
        }
    }
    
    protected function getUserPurchasedProducts(User $user)
    {
        return $user->orders()
            ->where('status', 'completed')
            ->with('items')
            ->get()
            ->pluck('items')
            ->flatten()
            ->pluck('product_id')
            ->unique();
    }
    
    public function trackRecommendationClick($recommendationId)
    {
        $recommendation = ProductRecommendation::find($recommendationId);
        if ($recommendation) {
            $recommendation->increment('click_count');
        }
    }
    
    public function trackRecommendationConversion($recommendationId)
    {
        $recommendation = ProductRecommendation::find($recommendationId);
        if ($recommendation) {
            $recommendation->increment('conversion_count');
            $recommendation->update([
                'conversion_rate' => $recommendation->conversion_count / max(1, $recommendation->click_count)
            ]);
        }
    }
}