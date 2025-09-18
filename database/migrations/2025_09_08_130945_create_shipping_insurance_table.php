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
        Schema::create('shipping_insurance', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., 'Basic Coverage', 'Premium Coverage'
            $table->text('description')->nullable();
            $table->decimal('min_order_value', 10, 2)->default(0); // Minimum order value to be eligible
            $table->decimal('max_order_value', 10, 2)->nullable(); // Maximum order value covered
            $table->decimal('coverage_percentage', 5, 2)->default(100.00); // % of order value covered
            $table->decimal('premium_percentage', 5, 2)->default(2.00); // % of order value charged as premium
            $table->decimal('minimum_premium', 8, 2)->default(10.00); // Minimum premium amount
            $table->decimal('maximum_premium', 8, 2)->nullable(); // Maximum premium amount
            $table->boolean('is_mandatory')->default(false); // Force insurance for certain conditions
            $table->json('conditions')->nullable(); // Special conditions/rules
            $table->boolean('is_active')->default(true);
            $table->integer('claim_processing_days')->default(7); // Days to process claims
            $table->timestamps();
            
            $table->index(['is_active', 'min_order_value', 'max_order_value'], 'shipping_insurance_order_value_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_insurance');
    }
};
