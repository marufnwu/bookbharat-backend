<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\HomepageSection;
use App\Models\HomepageLayout;

class HomepageSectionSeeder extends Seeder
{
    /**
     * Run the database seeder.
     *
     * Creates default homepage sections based on actual frontend implementation
     */
    public function run(): void
    {
        $this->command->info('Creating homepage sections...');

        // Define default sections (matching frontend implementation)
        $sections = [
            [
                'section_id' => 'hero',
                'section_type' => 'hero',
                'title' => 'Hero Section',
                'subtitle' => 'Main hero banner with CTA buttons',
                'enabled' => true,
                'order' => 1,
                'settings' => [
                    'variant' => 'minimal-product',
                    'show_stats' => true,
                    'show_cta' => true,
                    'stats' => [
                        ['label' => 'Books', 'value' => '500K+', 'icon' => 'book'],
                        ['label' => 'Happy Readers', 'value' => '100K+', 'icon' => 'users'],
                        ['label' => 'Rating', 'value' => '4.8★', 'icon' => 'star']
                    ]
                ],
                'styles' => [
                    'background' => 'gradient',
                    'text_color' => 'dark',
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
                    'banners' => [
                        [
                            'icon' => 'truck',
                            'title' => 'Free Shipping',
                            'description' => 'On orders above ₹500'
                        ],
                        [
                            'icon' => 'refresh-cw',
                            'title' => 'Easy Returns',
                            'description' => '7-day return policy'
                        ],
                        [
                            'icon' => 'shield-check',
                            'title' => 'Secure Payment',
                            'description' => '100% secure transactions'
                        ],
                        [
                            'icon' => 'headphones',
                            'title' => '24/7 Support',
                            'description' => 'Dedicated customer service'
                        ]
                    ]
                ],
                'styles' => [
                    'background' => 'light',
                    'icon_color' => 'primary',
                ],
            ],
            [
                'section_id' => 'featured-books',
                'section_type' => 'featured-products',
                'title' => 'Featured Books',
                'subtitle' => 'Discover our handpicked selection of must-read books',
                'enabled' => true,
                'order' => 3,
                'settings' => [
                    'limit' => 8,
                    'mobile_limit' => 6,
                    'layout' => 'grid',
                    'columns' => 4,
                    'mobile_columns' => 2,
                    'show_rating' => true,
                    'show_discount' => true,
                    'show_view_all' => true,
                    'view_all_link' => '/products?featured=true',
                ],
                'styles' => [
                    'background' => 'white',
                    'card_style' => 'elevated',
                ],
            ],
            [
                'section_id' => 'categories',
                'section_type' => 'categories',
                'title' => 'Browse by Category',
                'subtitle' => 'Discover your next favorite book from our wide range of categories',
                'enabled' => true,
                'order' => 4,
                'settings' => [
                    // Display Configuration
                    'initial_display' => 8,           // Show 8 categories initially (desktop)
                    'initial_display_mobile' => 6,    // Show 6 categories on mobile (2 rows x 3)
                    'show_all_toggle' => true,        // Enable "Show All/Show Less" toggle
                    'layout' => 'grid',
                    'mobile_layout' => 'slider-rows', // Options: grid, slider-rows, slider-single

                    // IMAGE-FOCUSED DESIGN 🖼️
                    'card_variant' => 'image-hero',   // NEW: image-hero, image-overlay, image-side, default
                    // Available variants:
                    // - image-hero: Large image on top with content below (4:3 aspect)
                    // - image-overlay: Text overlaid on square image with gradient
                    // - image-side: Image on left, content on right (horizontal)
                    // - default: Small icon/image with text (classic)

                    'columns' => [
                        'mobile' => 2,                // 2 columns on mobile
                        'sm' => 3,                    // 3 columns on small screens
                        'md' => 4,                    // 4 columns on medium screens
                        'lg' => 4,                    // 4 columns on large screens
                    ],
                    'gap' => [
                        'mobile' => 4,                // gap-4 on mobile
                        'md' => 6,                    // gap-6 on desktop
                    ],

                    // Image Settings
                    'image_aspect_ratio' => '4:3',    // Image aspect ratio for hero variant
                    'image_quality' => 85,            // Image quality (1-100)
                    'show_gradient_overlay' => true,  // Show gradient overlay on images
                    'lazy_load_images' => true,       // Enable lazy loading

                    // Content Display
                    'show_description' => false,      // Don't show category description
                    'show_product_count' => true,     // Show book count
                    'show_icons' => true,             // Auto-generate icons (fallback if no image)
                    'show_featured_badge' => true,    // Show "Featured" badge
                    'show_trending_badge' => true,    // Show "Popular" badge (>100 books)
                    'color_scheme' => 'gradient',     // Use colorful gradient scheme

                    // Mobile-Specific Settings 📱
                    'mobile_card_size' => '96px',     // 96x96px cards (w-24 h-24)
                    'mobile_slider_rows' => 2,        // 2 rows on mobile
                    'mobile_show_navigation' => true, // Show left/right arrows
                    'mobile_scroll_smooth' => true,   // Smooth scrolling
                    'mobile_hide_scrollbar' => true,  // Hide browser scrollbar
                    'mobile_touch_scroll' => true,    // Enable touch scrolling

                    // Action Buttons
                    'show_browse_all_button' => true, // Show "Browse All Categories" button
                    'browse_all_link' => '/categories',
                ],
                'styles' => [
                    'background' => 'gradient-decorated', // Gradient with decorative blobs
                    'section_padding' => 'py-12 md:py-16',
                    'header_style' => 'centered-with-icon',
                    'header_icon' => 'image',         // Use image icon in header
                    'card_hover_effect' => 'lift-shadow-scale', // Enhanced hover with scale
                    'decorative_elements' => true,    // Show gradient blobs
                    'card_border_radius' => 'rounded-lg',
                ],
            ],
            [
                'section_id' => 'category-products',
                'section_type' => 'category-products',
                'title' => 'Shop by Categories',
                'subtitle' => 'Explore our collection by genre',
                'enabled' => true,
                'order' => 5,
                'settings' => [
                    'products_per_category' => 4,
                    'mobile_products' => 2,
                    'show_see_all_button' => true,
                    'show_product_rating' => true,
                    'show_product_discount' => true,
                    'lazy_load' => true,
                    'categories_to_show' => 'all', // or specific category IDs
                ],
                'styles' => [
                    'background' => 'white',
                    'spacing' => 'comfortable',
                ],
            ],
            [
                'section_id' => 'newsletter',
                'section_type' => 'newsletter',
                'title' => 'Stay Updated',
                'subtitle' => 'Subscribe for latest releases, exclusive offers, and reading recommendations',
                'enabled' => true,
                'order' => 6,
                'settings' => [
                    'show_privacy_text' => true,
                    'background_style' => 'gradient',
                    'placeholder' => 'Enter your email address',
                    'button_text' => 'Subscribe',
                    'privacy_text' => 'We respect your privacy. Unsubscribe at any time.',
                    'success_message' => 'Thank you for subscribing!',
                ],
                'styles' => [
                    'background' => 'primary-gradient',
                    'text_color' => 'dark',
                    'input_style' => 'rounded',
                ],
            ],
            [
                'section_id' => 'cta-banner',
                'section_type' => 'cta-banner',
                'title' => 'Ready to Start Reading?',
                'subtitle' => 'Join millions of readers who trust us for their reading needs. Discover your next favorite book today!',
                'enabled' => true,
                'order' => 7,
                'settings' => [
                    'background_color' => 'primary',
                    'show_button' => true,
                    'button_text' => 'Shop Now',
                    'button_url' => '/products',
                    'show_icon' => true,
                    'button_icon' => 'arrow-right',
                ],
                'styles' => [
                    'background' => 'solid-primary',
                    'text_color' => 'white',
                    'button_variant' => 'secondary',
                ],
            ],
        ];

        // Create/update each section
        foreach ($sections as $section) {
            HomepageSection::updateOrCreate(
                ['section_id' => $section['section_id']],
                $section
            );
            $this->command->info("✅ Created/updated section: {$section['title']}");
        }

        // Create default homepage layout
        $this->command->info('Creating default homepage layout...');

        HomepageLayout::updateOrCreate(
            ['slug' => 'default'],
            [
                'name' => 'Default Layout',
                'description' => 'Default homepage layout with all essential sections',
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
                    'mobile_optimized' => true,
                    'lazy_load_enabled' => true,
                ],
                'draft_layout' => null,
                'published_at' => now(),
            ]
        );

        $this->command->info('✅ Homepage layout created successfully!');
        $this->command->newLine();
        $this->command->info('📝 Summary:');
        $this->command->line('   - ' . count($sections) . ' homepage sections created');
        $this->command->line('   - 1 default layout created and activated');
        $this->command->line('   - All sections are enabled by default');
        $this->command->newLine();
        $this->command->info('💡 Tip: Manage sections via Admin Panel → Homepage Layout');
    }
}
