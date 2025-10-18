<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HeroConfiguration;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HeroConfigController extends Controller
{
    /**
     * Get hero configuration
     */
    public function index()
    {
        try {
            $heroConfigs = HeroConfiguration::all();

            // Load featured products data for each config
            $heroConfigs->each(function ($config) {
                $this->loadFeaturedProducts($config);
            });

            return response()->json([
                'success' => true,
                'data' => $heroConfigs
            ], 200);

        } catch (\Exception $e) {
            Log::error('Hero config retrieval error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Unable to retrieve hero configurations.',
            ], 500);
        }
    }

    /**
     * Get specific hero configuration
     */
    public function show($variant)
    {
        try {
            $config = HeroConfiguration::where('variant', $variant)->first();

            if (!$config) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hero configuration not found.',
                ], 404);
            }

            // Load featured products data
            $this->loadFeaturedProducts($config);

            return response()->json([
                'success' => true,
                'data' => $config
            ], 200);

        } catch (\Exception $e) {
            Log::error('Hero config retrieval error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Unable to retrieve hero configuration.',
            ], 500);
        }
    }

    /**
     * Get active hero configuration
     */
    public function getActive()
    {
        try {
            $activeConfig = HeroConfiguration::where('is_active', true)->first();

            if (!$activeConfig) {
                // Fallback to first config
                $activeConfig = HeroConfiguration::first();
            }

            // Load featured products data
            if ($activeConfig) {
                $this->loadFeaturedProducts($activeConfig);
            }

            return response()->json([
                'success' => true,
                'data' => $activeConfig
            ], 200);

        } catch (\Exception $e) {
            Log::error('Active hero config retrieval error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Unable to retrieve active hero configuration.',
            ], 500);
        }
    }

    /**
     * Load featured products data for a hero configuration
     */
    private function loadFeaturedProducts($config)
    {
        if ($config && !empty($config->featuredProducts) && is_array($config->featuredProducts)) {
            try {
                $productIds = $config->featuredProducts;
                $products = Product::with(['images', 'category'])
                    ->whereIn('id', $productIds)
                    ->where('is_active', true)
                    ->get()
                    ->map(function ($product) {
                        return [
                            'id' => $product->id,
                            'name' => $product->name,
                            'slug' => $product->slug,
                            'price' => $product->price,
                            'sale_price' => $product->sale_price,
                            'short_description' => $product->short_description,
                            'images' => $product->images,
                            'category' => $product->category,
                            'average_rating' => $product->average_rating,
                            'total_reviews' => $product->total_reviews,
                            'stock_quantity' => $product->stock_quantity,
                        ];
                    })
                    ->toArray();

                // Preserve the order specified in featuredProducts
                $orderedProducts = [];
                foreach ($productIds as $id) {
                    $foundProduct = collect($products)->firstWhere('id', $id);
                    if ($foundProduct) {
                        $orderedProducts[] = $foundProduct;
                    }
                }

                $config->featuredProducts = $orderedProducts;
            } catch (\Exception $e) {
                Log::error('Error loading featured products: ' . $e->getMessage());
                // Keep the original array if loading fails
            }
        }
    }

}
