<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bundle_discount_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->unsignedInteger('min_products'); // Minimum number of products for this rule
            $table->unsignedInteger('max_products')->nullable(); // Maximum number of products (null = unlimited)
            $table->decimal('discount_percentage', 5, 2); // Discount percentage (e.g., 10.00 for 10%)
            $table->decimal('fixed_discount', 10, 2)->nullable(); // Alternative: fixed discount amount
            $table->enum('discount_type', ['percentage', 'fixed'])->default('percentage');
            $table->unsignedBigInteger('category_id')->nullable(); // Apply to specific category only
            $table->string('customer_tier')->nullable(); // Apply to specific customer tier
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0); // Higher priority rules apply first
            $table->datetime('valid_from')->nullable();
            $table->datetime('valid_until')->nullable();
            $table->json('conditions')->nullable(); // Additional conditions (e.g., specific products, brands)
            $table->text('description')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['min_products', 'is_active']);
            $table->index(['category_id', 'is_active']);
            $table->index(['valid_from', 'valid_until']);
            $table->index('priority');
            
            // Foreign key
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
        });

        // Insert default bundle discount rules
        DB::table('bundle_discount_rules')->insert([
            [
                'name' => '2 Products Bundle',
                'min_products' => 2,
                'max_products' => 2,
                'discount_percentage' => 5.00,
                'discount_type' => 'percentage',
                'is_active' => true,
                'priority' => 1,
                'description' => 'Get 5% off when buying 2 products together',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => '3 Products Bundle',
                'min_products' => 3,
                'max_products' => 3,
                'discount_percentage' => 10.00,
                'discount_type' => 'percentage',
                'is_active' => true,
                'priority' => 2,
                'description' => 'Get 10% off when buying 3 products together',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => '4 Products Bundle',
                'min_products' => 4,
                'max_products' => 4,
                'discount_percentage' => 12.00,
                'discount_type' => 'percentage',
                'is_active' => true,
                'priority' => 3,
                'description' => 'Get 12% off when buying 4 products together',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Bulk Bundle (5+)',
                'min_products' => 5,
                'max_products' => null,
                'discount_percentage' => 15.00,
                'discount_type' => 'percentage',
                'is_active' => true,
                'priority' => 4,
                'description' => 'Get 15% off when buying 5 or more products together',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Weekend Special',
                'min_products' => 2,
                'max_products' => null,
                'discount_percentage' => 20.00,
                'discount_type' => 'percentage',
                'is_active' => false, // Disabled by default
                'priority' => 10, // Higher priority when active
                'description' => 'Special 20% off on all bundles during weekends',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bundle_discount_rules');
    }
};