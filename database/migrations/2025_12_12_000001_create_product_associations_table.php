<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_associations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('associated_product_id');
            $table->unsignedInteger('frequency')->default(1);
            $table->decimal('confidence_score', 3, 2)->default(0.00);
            $table->enum('association_type', ['bought_together', 'viewed_together', 'in_same_category'])->default('bought_together');
            $table->timestamp('last_purchased_together')->nullable();
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('associated_product_id')->references('id')->on('products')->onDelete('cascade');
            
            // Indexes
            $table->unique(['product_id', 'associated_product_id', 'association_type'], 'unique_product_association');
            $table->index(['product_id', 'frequency'], 'idx_product_frequency');
            $table->index(['product_id', 'confidence_score'], 'idx_product_confidence');
            $table->index('association_type');
        });

        // Create bundle analytics table
        Schema::create('bundle_analytics', function (Blueprint $table) {
            $table->id();
            $table->string('bundle_id')->unique();
            $table->json('product_ids');
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('add_to_cart')->default(0);
            $table->unsignedInteger('purchases')->default(0);
            $table->decimal('total_revenue', 10, 2)->default(0);
            $table->decimal('conversion_rate', 5, 2)->default(0);
            $table->timestamps();
            
            $table->index('bundle_id');
            $table->index('conversion_rate');
        });

        // Create related products override table (for manual curation)
        Schema::create('related_products_overrides', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('related_product_id');
            $table->unsignedInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('related_product_id')->references('id')->on('products')->onDelete('cascade');
            
            $table->unique(['product_id', 'related_product_id']);
            $table->index(['product_id', 'priority', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('related_products_overrides');
        Schema::dropIfExists('bundle_analytics');
        Schema::dropIfExists('product_associations');
    }
};