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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('status')->default('pending'); // pending, processing, shipped, delivered, cancelled, refunded
            $table->decimal('subtotal', 10, 2);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('shipping_amount', 10, 2)->default(0);
            $table->string('shipping_zone', 10)->nullable();
            $table->foreignId('delivery_option_id')->nullable()->constrained('delivery_options')->nullOnDelete();
            $table->decimal('insurance_amount', 10, 2)->default(0);
            $table->json('shipping_details')->nullable();
            $table->string('pickup_pincode', 10)->nullable();
            $table->string('delivery_pincode', 10)->nullable();
            $table->date('estimated_delivery_date')->nullable();
            $table->string('tracking_number', 100)->nullable();
            $table->string('courier_partner', 100)->nullable();
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->foreignId('referral_code_id')->nullable()->constrained('referral_codes')->onDelete('set null');
            $table->decimal('referral_discount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2);
            $table->string('currency', 3)->default('INR');
            $table->string('payment_status')->default('pending'); // pending, paid, failed, refunded
            $table->string('payment_method')->nullable(); // razorpay, cashfree, cod
            $table->string('payment_transaction_id')->nullable();
            $table->json('billing_address');
            $table->json('shipping_address');
            $table->text('notes')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'payment_status']);
            $table->index('order_number');
            $table->index('shipping_zone');
            $table->index('tracking_number');
            $table->index('referral_code_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
