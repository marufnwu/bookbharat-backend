<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\HomepageSection;
use App\Models\HomepageLayout;

class HomepageSectionSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        // Create default sections
        $sections = [
            [
                'section_id' => 'hero',
                'section_type' => 'hero',
                'title' => 'Hero Section',
                'subtitle' => 'Main banner with call-to-action',
                'enabled' => true,
                'order' => 1,
                'settings' => [
                    'variant' => 'minimal-product',
                    'show_stats' => true,
                    'show_cta' => true,
                ],
            ],
            [
                'section_id' => 'promotional-banners',
                'section_type' => 'promotional-banners',
                'title' => 'Promotional Banners',
                'subtitle' => 'Feature highlights (shipping, returns, security)',
                'enabled' => true,
                'order' => 2,
                'settings' => [
                    'layout' => 'horizontal',
                    'show_icons' => true,
                ],
            ],
            [
                'section_id' => 'featured-books',
                'section_type' => 'featured-products',
                'title' => 'Featured Books',
                'subtitle' => 'Discover our handpicked selection',
                'enabled' => true,
                'order' => 3,
                'settings' => [
                    'limit' => 8,
                    'layout' => 'grid',
                    'columns' => 4,
                    'show_rating' => true,
                    'show_discount' => true,
                ],
            ],
            [
                'section_id' => 'categories',
                'section_type' => 'categories',
                'title' => 'Browse by Category',
                'subtitle' => 'Find books in your favorite genres',
                'enabled' => true,
                'order' => 4,
                'settings' => [
                    'limit' => 8,
                    'layout' => 'grid',
                    'show_count' => true,
                    'show_icons' => true,
                ],
            ],
            [
                'section_id' => 'category-products',
                'section_type' => 'category-products',
                'title' => 'Shop by Categories',
                'subtitle' => 'Explore our collection',
                'enabled' => true,
                'order' => 5,
                'settings' => [
                    'products_per_category' => 4,
                    'show_see_all' => true,
                    'lazy_load' => true,
                ],
            ],
            [
                'section_id' => 'newsletter',
                'section_type' => 'newsletter',
                'title' => 'Stay Updated',
                'subtitle' => 'Subscribe for latest releases and offers',
                'enabled' => true,
                'order' => 6,
                'settings' => [
                    'show_privacy_text' => true,
                    'background_style' => 'gradient',
                ],
            ],
            [
                'section_id' => 'cta-banner',
                'section_type' => 'cta-banner',
                'title' => 'Ready to Start Reading?',
                'subtitle' => 'Join millions of readers',
                'enabled' => true,
                'order' => 7,
                'settings' => [
                    'background_color' => 'primary',
                    'show_button' => true,
                    'button_text' => 'Shop Now',
                    'button_url' => '/products',
                ],
            ],
        ];

        foreach ($sections as $section) {
            HomepageSection::updateOrCreate(
                ['section_id' => $section['section_id']],
                $section
            );
        }

        // Create default layout
        HomepageLayout::updateOrCreate(
            ['slug' => 'default'],
            [
                'name' => 'Default Layout',
                'description' => 'Default homepage layout',
                'is_active' => true,
                'layout' => [
                    'sections' => [
                        'hero',
                        'promotional-banners',
                        'featured-books',
                        'categories',
                        'category-products',
                        'newsletter',
                        'cta-banner',
                    ],
                ],
                'published_at' => now(),
            ]
        );

        $this->command->info('Homepage sections and default layout created successfully!');
    }
}

