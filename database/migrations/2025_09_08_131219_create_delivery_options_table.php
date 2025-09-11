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
        Schema::create('delivery_options', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., 'Standard', 'Express', 'Same Day', 'Next Day'
            $table->string('code')->unique(); // e.g., 'standard', 'express', 'same_day', 'next_day'
            $table->text('description')->nullable();
            $table->integer('delivery_days_min')->default(1); // Minimum delivery days
            $table->integer('delivery_days_max')->default(3); // Maximum delivery days
            $table->decimal('price_multiplier', 5, 2)->default(1.00); // Multiplier for base shipping cost
            $table->decimal('fixed_surcharge', 8, 2)->default(0); // Fixed additional cost
            $table->json('availability_zones')->nullable(); // Zones where this option is available
            $table->json('availability_conditions')->nullable(); // Conditions for availability
            $table->time('cutoff_time')->nullable(); // Order cutoff time for same-day delivery
            $table->json('restricted_days')->nullable(); // Days when not available (e.g., Sundays)
            $table->decimal('min_order_value', 10, 2)->default(0); // Minimum order value required
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->index(['is_active', 'code']);
            $table->index(['min_order_value', 'availability_zones']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_options');
    }
};
