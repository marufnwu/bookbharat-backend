<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class HeroConfigController extends Controller
{
    /**
     * Get hero configuration
     */
    public function index()
    {
        try {
            $heroConfigs = Cache::get('hero_configs', $this->getDefaultHeroConfigs());

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
            $heroConfigs = Cache::get('hero_configs', $this->getDefaultHeroConfigs());
            $config = collect($heroConfigs)->firstWhere('variant', $variant);

            if (!$config) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hero configuration not found.',
                ], 404);
            }

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
     * Update hero configuration
     */
    public function update(Request $request, $variant)
    {
        try {
            $request->validate([
                'title' => 'required|string|max:255',
                'subtitle' => 'required|string|max:500',
                'primaryCta.text' => 'required|string|max:100',
                'primaryCta.href' => 'required|string|max:255',
                'secondaryCta.text' => 'nullable|string|max:100',
                'secondaryCta.href' => 'nullable|string|max:255',
                'stats' => 'nullable|array',
                'stats.*.label' => 'required|string|max:100',
                'stats.*.value' => 'required|string|max:50',
                'stats.*.icon' => 'nullable|string|max:50',
                'backgroundImage' => 'nullable|string|max:500',
            ]);

            $heroConfigs = Cache::get('hero_configs', $this->getDefaultHeroConfigs());
            $configIndex = collect($heroConfigs)->search(function ($config) use ($variant) {
                return $config['variant'] === $variant;
            });

            if ($configIndex === false) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hero configuration not found.',
                ], 404);
            }

            $heroConfigs[$configIndex] = array_merge($heroConfigs[$configIndex], [
                'title' => $request->title,
                'subtitle' => $request->subtitle,
                'primaryCta' => $request->primaryCta,
                'secondaryCta' => $request->secondaryCta,
                'stats' => $request->stats,
                'backgroundImage' => $request->backgroundImage,
                'updated_at' => now()->toISOString()
            ]);

            Cache::put('hero_configs', $heroConfigs);

            return response()->json([
                'success' => true,
                'message' => 'Hero configuration updated successfully.',
                'data' => $heroConfigs[$configIndex]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Hero config update error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Unable to update hero configuration.',
            ], 500);
        }
    }

    /**
     * Get active hero configuration
     */
    public function getActive()
    {
        try {
            $activeVariant = Cache::get('active_hero_variant', 'minimal-product');
            $heroConfigs = Cache::get('hero_configs', $this->getDefaultHeroConfigs());
            $activeConfig = collect($heroConfigs)->firstWhere('variant', $activeVariant);

            if (!$activeConfig) {
                $activeConfig = $heroConfigs[0]; // Fallback to first config
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
     * Set active hero configuration
     */
    public function setActive(Request $request)
    {
        try {
            $request->validate([
                'variant' => 'required|string|in:minimal-product,lifestyle-storytelling,interactive-promotional,category-grid,seasonal-campaign,product-highlight,video-hero,interactive-tryOn,editorial-magazine,classic,modern,minimal'
            ]);

            Cache::put('active_hero_variant', $request->variant);

            return response()->json([
                'success' => true,
                'message' => 'Active hero variant updated successfully.',
                'data' => ['active_variant' => $request->variant]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Set active hero error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Unable to set active hero variant.',
            ], 500);
        }
    }

    /**
     * Get default hero configurations
     */
    private function getDefaultHeroConfigs()
    {
        return [
            [
                'variant' => 'minimal-product',
                'title' => 'Premium Books at Unbeatable Prices',
                'subtitle' => 'Discover our curated collection of bestsellers and classics with fast, free shipping.',
                'primaryCta' => [
                    'text' => 'Shop Now',
                    'href' => '/products'
                ],
                'secondaryCta' => [
                    'text' => 'Browse Categories',
                    'href' => '/categories'
                ],
                'discountBadge' => [
                    'text' => '50% OFF',
                    'color' => 'red'
                ],
                'trustBadges' => ['Free Shipping', 'Easy Returns', '24/7 Support'],
                'featuredProducts' => [],
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString()
            ],
            [
                'variant' => 'lifestyle-storytelling',
                'title' => 'Every Page Tells a Story',
                'subtitle' => 'Immerse yourself in worlds of imagination and knowledge. Join millions of readers who trust us for their literary journey.',
                'primaryCta' => [
                    'text' => 'Start Your Journey',
                    'href' => '/products'
                ],
                'backgroundImage' => '/images/lifestyle-reading.jpg',
                'testimonials' => [
                    [
                        'text' => 'BookBharat has completely transformed my reading habits. The quality and variety are unmatched.',
                        'author' => 'Sarah Johnson, Avid Reader',
                        'rating' => 5
                    ]
                ],
                'featuredProducts' => [],
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString()
            ],
            [
                'variant' => 'interactive-promotional',
                'title' => 'Limited Time: Book Festival Sale',
                'subtitle' => 'Up to 70% off on bestsellers, classics, and new releases. Free shipping on all orders above $50.',
                'primaryCta' => [
                    'text' => 'Grab Deals Now',
                    'href' => '/products?sale=true'
                ],
                'secondaryCta' => [
                    'text' => 'View All Offers',
                    'href' => '/deals'
                ],
                'campaignData' => [
                    'title' => '📚 MEGA BOOK SALE',
                    'offer' => 'Up to 70% OFF',
                    'countdown' => '2024-12-31T23:59:59Z'
                ],
                'featuredProducts' => [],
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString()
            ],
            [
                'variant' => 'category-grid',
                'title' => 'Explore Our Book Universe',
                'subtitle' => 'From fiction to non-fiction, academic to leisure - find your perfect book category.',
                'primaryCta' => [
                    'text' => 'View All Categories',
                    'href' => '/categories'
                ],
                'categories' => [
                    [
                        'id' => '1',
                        'name' => 'Fiction',
                        'image' => '/images/categories/fiction.jpg',
                        'href' => '/categories/fiction'
                    ],
                    [
                        'id' => '2',
                        'name' => 'Non-Fiction',
                        'image' => '/images/categories/non-fiction.jpg',
                        'href' => '/categories/non-fiction'
                    ],
                    [
                        'id' => '3',
                        'name' => 'Academic',
                        'image' => '/images/categories/academic.jpg',
                        'href' => '/categories/academic'
                    ],
                    [
                        'id' => '4',
                        'name' => 'Children',
                        'image' => '/images/categories/children.jpg',
                        'href' => '/categories/children'
                    ]
                ],
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString()
            ],
            [
                'variant' => 'seasonal-campaign',
                'title' => 'Winter Reading Festival 2024',
                'subtitle' => 'Warm up your winter nights with our specially curated collection. Limited time offers on cozy reads.',
                'primaryCta' => [
                    'text' => 'Shop Winter Collection',
                    'href' => '/collections/winter-2024'
                ],
                'backgroundImage' => '/images/winter-reading-bg.jpg',
                'campaignData' => [
                    'title' => '❄️ WINTER SALE',
                    'offer' => 'Buy 2 Get 1 Free',
                    'countdown' => '2024-12-31T23:59:59Z'
                ],
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString()
            ],
            [
                'variant' => 'product-highlight',
                'title' => 'Book of the Month: The Silent Patient',
                'subtitle' => 'A psychological thriller that will keep you guessing until the very last page. Now available with exclusive bonus content.',
                'primaryCta' => [
                    'text' => 'Buy Now',
                    'href' => '/products/the-silent-patient'
                ],
                'secondaryCta' => [
                    'text' => 'Read Sample',
                    'href' => '/products/the-silent-patient/preview'
                ],
                'features' => [
                    [
                        'title' => 'Bestseller',
                        'description' => '#1 New York Times Bestseller',
                        'icon' => 'star'
                    ],
                    [
                        'title' => 'Fast Shipping',
                        'description' => 'Free 2-day delivery',
                        'icon' => 'truck'
                    ],
                    [
                        'title' => 'Bonus Content',
                        'description' => 'Exclusive author interview',
                        'icon' => 'award'
                    ],
                    [
                        'title' => 'Money Back',
                        'description' => '30-day return guarantee',
                        'icon' => 'shield'
                    ]
                ],
                'featuredProducts' => [],
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString()
            ],
            [
                'variant' => 'video-hero',
                'title' => 'Experience Reading Like Never Before',
                'subtitle' => 'Join our community of book lovers and discover your next favorite read with personalized recommendations.',
                'primaryCta' => [
                    'text' => 'Watch Our Story',
                    'href' => '/about'
                ],
                'secondaryCta' => [
                    'text' => 'Start Shopping',
                    'href' => '/products'
                ],
                'videoUrl' => '/videos/bookbharat-story.mp4',
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString()
            ],
            [
                'variant' => 'interactive-tryOn',
                'title' => 'Try Before You Buy',
                'subtitle' => 'Preview any book with our interactive reader. Get a taste of the content before making your purchase.',
                'primaryCta' => [
                    'text' => 'Start Previewing',
                    'href' => '/products?preview=true'
                ],
                'features' => [
                    [
                        'title' => 'Full Preview',
                        'description' => 'Read first 20 pages',
                        'icon' => 'eye'
                    ],
                    [
                        'title' => 'Audio Sample',
                        'description' => 'Listen to excerpts',
                        'icon' => 'phone'
                    ],
                    [
                        'title' => 'Reviews',
                        'description' => 'Real reader feedback',
                        'icon' => 'users'
                    ],
                    [
                        'title' => 'Recommendations',
                        'description' => 'Similar book suggestions',
                        'icon' => 'heart'
                    ]
                ],
                'featuredProducts' => [],
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString()
            ],
            [
                'variant' => 'editorial-magazine',
                'title' => 'The BookBharat Review',
                'subtitle' => 'Curated literary excellence meets modern convenience. Discover hand-picked selections from our editorial team.',
                'primaryCta' => [
                    'text' => 'Read Full Review',
                    'href' => '/editorial/featured'
                ],
                'stats' => [
                    [
                        'label' => 'Books Reviewed',
                        'value' => '2,500+',
                        'icon' => 'book'
                    ],
                    [
                        'label' => 'Expert Critics',
                        'value' => '25',
                        'icon' => 'users'
                    ],
                    [
                        'label' => 'Awards Won',
                        'value' => '50+',
                        'icon' => 'award'
                    ],
                    [
                        'label' => 'Years Experience',
                        'value' => '15+',
                        'icon' => 'trending'
                    ]
                ],
                'testimonials' => [
                    [
                        'text' => 'BookBharat\'s editorial team has never steered me wrong. Their recommendations are always spot-on.',
                        'author' => 'Michael Chen, Literature Professor',
                        'rating' => 5
                    ]
                ],
                'featuredProducts' => [],
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString()
            ]
        ];
    }
}
