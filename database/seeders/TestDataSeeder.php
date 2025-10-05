<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PromotionalCampaign;
use App\Models\DeliveryOption;
use App\Models\UserGeneratedContent;
use App\Models\Review;
use App\Models\ProductAssociation;
use App\Models\HeroConfiguration;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        echo "Seeding test data for API endpoints...\n";

        $this->seedPromotionalCampaigns();
        $this->seedDeliveryOptions();
        $this->seedReviews();
        $this->seedProductAssociations();
        $this->seedHeroConfigurations();
        $this->seedUserGeneratedContent();

        echo "Test data seeding complete!\n";
    }

    /**
     * Seed promotional campaigns
     */
    private function seedPromotionalCampaigns(): void
    {
        echo "Seeding promotional campaigns...\n";

        // Check if table exists
        if (!DB::getSchemaBuilder()->hasTable('promotional_campaigns')) {
            echo "  ⚠️  Table 'promotional_campaigns' does not exist. Skipping.\n";
            return;
        }

        PromotionalCampaign::create([
            'name' => 'Summer Sale 2025',
            'slug' => 'summer-sale-2025',
            'description' => 'Big summer discount on all books',
            'type' => 'seasonal_offer',
            'status' => 'active',
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->addDays(20),
            'campaign_rules' => json_encode([
                'discount_type' => 'percentage',
                'discount_value' => 20,
                'min_purchase' => 500
            ]),
            'target_audience' => json_encode(['customer_groups' => [1, 2]]),
            'budget_limit' => 50000,
            'current_spend' => 12000,
            'created_by' => 1,
        ]);

        PromotionalCampaign::create([
            'name' => 'New Year Special',
            'slug' => 'new-year-special-2025',
            'description' => 'Welcome 2025 with book bundles',
            'type' => 'bundle_deal',
            'status' => 'ended',
            'starts_at' => now()->subMonths(3),
            'ends_at' => now()->subMonths(2),
            'campaign_rules' => json_encode([
                'discount_type' => 'fixed',
                'discount_value' => 100,
                'bundle_qty' => 3
            ]),
            'target_audience' => json_encode(['customer_groups' => [2]]),
            'budget_limit' => 100000,
            'current_spend' => 95000,
            'created_by' => 1,
        ]);

        echo "  ✓ Seeded 2 promotional campaigns\n";
    }

    /**
     * Seed delivery options
     */
    private function seedDeliveryOptions(): void
    {
        echo "Seeding delivery options...\n";

        // Check if table exists
        if (!DB::getSchemaBuilder()->hasTable('delivery_options')) {
            echo "  ⚠️  Table 'delivery_options' does not exist. Skipping.\n";
            return;
        }

        DeliveryOption::create([
            'name' => 'Standard Delivery',
            'description' => 'Delivery within 5-7 business days',
            'type' => 'standard',
            'min_delivery_days' => 5,
            'max_delivery_days' => 7,
            'surcharge_type' => 'percentage',
            'surcharge_value' => 0,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        DeliveryOption::create([
            'name' => 'Express Delivery',
            'description' => 'Fast delivery within 2-3 business days',
            'type' => 'express',
            'min_delivery_days' => 2,
            'max_delivery_days' => 3,
            'surcharge_type' => 'fixed',
            'surcharge_value' => 50,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        DeliveryOption::create([
            'name' => 'Same Day Delivery',
            'description' => 'Get it today (selected cities only)',
            'type' => 'same_day',
            'min_delivery_days' => 0,
            'max_delivery_days' => 0,
            'surcharge_type' => 'fixed',
            'surcharge_value' => 150,
            'is_active' => true,
            'sort_order' => 3,
        ]);

        echo "  ✓ Seeded 3 delivery options\n";
    }

    /**
     * Seed reviews
     */
    private function seedReviews(): void
    {
        echo "Seeding reviews...\n";

        // Check if reviews table has status column
        if (!DB::getSchemaBuilder()->hasColumn('reviews', 'status')) {
            echo "  ⚠️  Reviews table missing 'status' column. Run migration first.\n";
            return;
        }

        $users = User::limit(5)->get();
        $products = Product::limit(10)->get();

        if ($users->isEmpty() || $products->isEmpty()) {
            echo "  ⚠️  No users or products found. Skipping review seeding.\n";
            return;
        }

        $created = 0;

        foreach ($users as $user) {
            $product = $products->random();

            Review::create([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'rating' => rand(3, 5),
                'title' => 'Great book!',
                'comment' => 'Really enjoyed reading this book. Highly recommended!',
                'is_verified_purchase' => true,
                'is_approved' => true,
                'status' => 'approved',
            ]);
            $created++;
        }

        // Create pending review
        Review::create([
            'user_id' => $users->first()->id,
            'product_id' => $products->random()->id,
            'rating' => 4,
            'title' => 'Pending review',
            'comment' => 'This review is pending moderation',
            'is_verified_purchase' => true,
            'is_approved' => false,
            'status' => 'pending',
        ]);
        $created++;

        // Create reported review
        Review::create([
            'user_id' => $users->last()->id,
            'product_id' => $products->random()->id,
            'rating' => 2,
            'title' => 'Reported review',
            'comment' => 'This review has been reported',
            'is_verified_purchase' => false,
            'is_approved' => true,
            'status' => 'approved',
            'is_reported' => true,
            'report_count' => 3,
        ]);
        $created++;

        echo "  ✓ Seeded {$created} reviews\n";
    }

    /**
     * Seed product associations
     */
    private function seedProductAssociations(): void
    {
        echo "Seeding product associations...\n";

        // Check if table exists
        if (!DB::getSchemaBuilder()->hasTable('product_associations')) {
            echo "  ⚠️  Table 'product_associations' does not exist. Skipping.\n";
            return;
        }

        $products = Product::limit(5)->get();

        if ($products->count() < 2) {
            echo "  ⚠️  Not enough products found. Skipping.\n";
            return;
        }

        $created = 0;

        for ($i = 0; $i < $products->count() - 1; $i++) {
            ProductAssociation::create([
                'product_id' => $products[$i]->id,
                'associated_product_id' => $products[$i + 1]->id,
                'association_type' => 'frequently_bought_together',
                'confidence_score' => rand(70, 95) / 100,
                'support_count' => rand(10, 50),
                'is_active' => true,
            ]);
            $created++;
        }

        echo "  ✓ Seeded {$created} product associations\n";
    }

    /**
     * Seed hero configurations
     */
    private function seedHeroConfigurations(): void
    {
        echo "Seeding hero configurations...\n";

        // Check if table exists
        if (!DB::getSchemaBuilder()->hasTable('hero_configurations')) {
            echo "  ⚠️  Table 'hero_configurations' does not exist. Skipping.\n";
            return;
        }

        HeroConfiguration::create([
            'name' => 'Default Hero',
            'title' => 'Your Knowledge Partner for Life',
            'subtitle' => 'Discover millions of books across all genres',
            'cta_text' => 'Explore Books',
            'cta_link' => '/products',
            'background_image' => '/images/hero-bg.jpg',
            'is_active' => true,
            'is_default' => true,
            'display_order' => 1,
        ]);

        HeroConfiguration::create([
            'name' => 'Summer Sale Hero',
            'title' => 'Summer Reading Sale!',
            'subtitle' => 'Up to 50% off on selected books',
            'cta_text' => 'Shop Now',
            'cta_link' => '/products?sale=true',
            'background_image' => '/images/summer-sale-hero.jpg',
            'is_active' => true,
            'is_default' => false,
            'display_order' => 2,
        ]);

        echo "  ✓ Seeded 2 hero configurations\n";
    }

    /**
     * Seed user generated content for moderation
     */
    private function seedUserGeneratedContent(): void
    {
        echo "Seeding user generated content...\n";

        // Check if table exists
        if (!DB::getSchemaBuilder()->hasTable('user_generated_content')) {
            echo "  ⚠️  Table 'user_generated_content' does not exist. Skipping.\n";
            return;
        }

        $users = User::limit(3)->get();

        if ($users->isEmpty()) {
            echo "  ⚠️  No users found. Skipping.\n";
            return;
        }

        UserGeneratedContent::create([
            'user_id' => $users->first()->id,
            'content_type' => 'review',
            'content' => 'This is user generated content for testing',
            'status' => 'pending',
            'is_featured' => false,
        ]);

        UserGeneratedContent::create([
            'user_id' => $users->last()->id,
            'content_type' => 'comment',
            'content' => 'Featured user content',
            'status' => 'approved',
            'is_featured' => true,
        ]);

        echo "  ✓ Seeded 2 user generated content items\n";
    }
}
