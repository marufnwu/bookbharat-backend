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
        Schema::create('cart_recovery_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('persistent_cart_id')->constrained('persistent_carts')->onDelete('cascade');
            $table->string('code')->unique();
            $table->enum('type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('value', 10, 2);
            $table->decimal('min_purchase_amount', 10, 2)->nullable();
            $table->decimal('max_discount_amount', 10, 2)->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->boolean('is_used')->default(false);
            $table->integer('usage_count')->default(0);
            $table->integer('max_usage_count')->default(1);
            $table->decimal('revenue_generated', 10, 2)->default(0);
            $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('set null');
            $table->timestamps();

            $table->index(['code', 'is_used']);
            $table->index(['persistent_cart_id']);
            $table->index(['valid_until', 'is_used']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_recovery_discounts');
    }
};
