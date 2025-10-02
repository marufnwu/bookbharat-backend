<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\HeroConfiguration;

class HeroConfigurationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $configurations = [
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
                'is_active' => true,
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
                'is_active' => false,
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
                'is_active' => false,
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
                'is_active' => false,
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
                'is_active' => false,
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
                'is_active' => false,
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
                'is_active' => false,
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
                'is_active' => false,
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
                'is_active' => false,
            ],
        ];

        foreach ($configurations as $config) {
            HeroConfiguration::create($config);
        }
    }
}
