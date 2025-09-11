<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RecommendationEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RecommendationController extends Controller
{
    protected RecommendationEngine $recommendationEngine;

    public function __construct(RecommendationEngine $recommendationEngine)
    {
        $this->recommendationEngine = $recommendationEngine;
    }

    /**
     * Get personalized product recommendations
     */
    public function getRecommendations(Request $request)
    {
        $user = Auth::user();
        $context = $request->get('context', 'general');
        $limit = $request->get('limit', 10);

        if (!$user) {
            return $this->getTrendingProducts($limit);
        }

        $recommendations = $this->recommendationEngine->generateRecommendations($user, $context, $limit);

        return response()->json([
            'success' => true,
            'data' => $recommendations->map(function ($rec) {
                return [
                    'product_id' => $rec['product']->id,
                    'name' => $rec['product']->name,
                    'price' => $rec['product']->price,
                    'image' => $rec['product']->primary_image,
                    'rating' => $rec['product']->average_rating,
                    'confidence_score' => $rec['score'],
                    'reasoning' => $rec['reasoning'],
                    'recommendation_types' => $rec['types'],
                ];
            }),
            'context' => $context,
            'total' => $recommendations->count()
        ]);
    }

    /**
     * Get recommendations for homepage
     */
    public function getHomepageRecommendations(Request $request)
    {
        $user = Auth::user();
        $limit = $request->get('limit', 12);

        if ($user) {
            $recommendations = $this->recommendationEngine->generateRecommendations($user, 'homepage', $limit);
        } else {
            $recommendations = $this->getTrendingProducts($limit);
        }

        return response()->json([
            'success' => true,
            'data' => $recommendations,
            'is_personalized' => $user !== null
        ]);
    }

    /**
     * Get product cross-sell recommendations  
     */
    public function getCrossSellRecommendations(Request $request, $productId)
    {
        $user = Auth::user();
        $limit = $request->get('limit', 6);
        
        // For now, get category-based recommendations
        // In full implementation, this would use collaborative filtering
        $product = \App\Models\Product::findOrFail($productId);
        $recommendations = \App\Models\Product::active()
            ->inStock()
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $productId)
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $recommendations,
            'source_product' => $product->name
        ]);
    }

    /**
     * Track recommendation click
     */
    public function trackClick(Request $request)
    {
        $request->validate([
            'recommendation_id' => 'required|exists:product_recommendations,id',
            'product_id' => 'required|exists:products,id'
        ]);

        $this->recommendationEngine->trackRecommendationClick($request->recommendation_id);

        return response()->json(['success' => true]);
    }

    /**
     * Track recommendation conversion
     */
    public function trackConversion(Request $request)
    {
        $request->validate([
            'recommendation_id' => 'required|exists:product_recommendations,id',
            'order_id' => 'required|exists:orders,id'
        ]);

        $this->recommendationEngine->trackRecommendationConversion($request->recommendation_id);

        return response()->json(['success' => true]);
    }

    /**
     * Get trending products for non-logged-in users
     */
    protected function getTrendingProducts($limit)
    {
        $trendingProducts = \App\Models\Product::active()
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

        return $trendingProducts->map(function ($product) {
            return [
                'product_id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'image' => $product->primary_image,
                'rating' => $product->average_rating,
                'confidence_score' => 0.5,
                'reasoning' => 'Trending product',
                'recommendation_types' => 'trending',
            ];
        });
    }
}