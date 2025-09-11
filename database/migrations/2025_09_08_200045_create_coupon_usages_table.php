<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->decimal('discount_amount', 10, 2);
            $table->decimal('order_total_before_discount', 10, 2);
            $table->decimal('order_total_after_discount', 10, 2);
            $table->json('applied_products')->nullable(); // which products the discount was applied to
            $table->json('usage_context')->nullable(); // additional context like campaign, referrer, etc.
            $table->timestamps();

            $table->index(['coupon_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_usages');
    }
};