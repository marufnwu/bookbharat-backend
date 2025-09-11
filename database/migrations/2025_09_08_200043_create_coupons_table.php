<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['percentage', 'fixed_amount', 'free_shipping', 'buy_x_get_y']);
            $table->decimal('value', 10, 2)->nullable(); // percentage or fixed amount
            $table->decimal('minimum_order_amount', 10, 2)->default(0);
            $table->decimal('maximum_discount_amount', 10, 2)->nullable();
            $table->integer('usage_limit')->nullable(); // null = unlimited
            $table->integer('usage_limit_per_customer')->nullable();
            $table->integer('usage_count')->default(0);
            $table->datetime('starts_at');
            $table->datetime('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_stackable')->default(false); // can combine with other coupons
            $table->json('applicable_products')->nullable(); // specific product IDs
            $table->json('applicable_categories')->nullable(); // specific category IDs
            $table->json('applicable_customer_groups')->nullable(); // specific customer group IDs
            $table->json('excluded_products')->nullable(); // excluded product IDs
            $table->json('excluded_categories')->nullable(); // excluded category IDs
            $table->enum('first_order_only', ['yes', 'no'])->default('no');
            $table->json('buy_x_get_y_config')->nullable(); // {buy_quantity: 2, get_quantity: 1, product_id: null}
            $table->json('geographic_restrictions')->nullable(); // allowed/excluded locations
            $table->json('day_time_restrictions')->nullable(); // specific days/hours
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['code', 'is_active']);
            $table->index(['starts_at', 'expires_at']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};