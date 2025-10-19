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
        Schema::create('product_bundle_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->string('name'); // e.g., "Pack of 2 Books"
            $table->string('sku')->unique(); // e.g., "BOOK-001-PACK2"
            $table->integer('quantity'); // number of items in bundle (e.g., 2, 3, 5)

            // Pricing configuration
            $table->enum('pricing_type', ['percentage_discount', 'fixed_price', 'fixed_discount']);
            $table->decimal('discount_percentage', 5, 2)->nullable(); // e.g., 10.00 for 10%
            $table->decimal('fixed_price', 10, 2)->nullable(); // e.g., 900.00 for ₹900
            $table->decimal('fixed_discount', 10, 2)->nullable(); // e.g., 100.00 for ₹100 off
            $table->decimal('compare_price', 10, 2)->nullable(); // optional original price for display

            // Stock management
            $table->enum('stock_management_type', ['use_main_product', 'separate_stock'])->default('use_main_product');
            $table->integer('stock_quantity')->default(0); // used only if separate_stock

            // Status and ordering
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);

            // Additional metadata
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['product_id', 'is_active']);
            $table->index('sku');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_bundle_variants');
    }
};

