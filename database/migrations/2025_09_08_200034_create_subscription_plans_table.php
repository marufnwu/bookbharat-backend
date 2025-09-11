<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('plan_name');
            $table->string('billing_interval'); // daily, weekly, monthly, quarterly, yearly
            $table->integer('billing_interval_count')->default(1); // every X intervals
            $table->decimal('price', 10, 2);
            $table->decimal('setup_fee', 10, 2)->default(0);
            $table->integer('trial_days')->default(0);
            $table->integer('billing_cycles')->nullable(); // null = unlimited
            $table->json('features')->nullable(); // plan-specific features
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['product_id', 'is_active']);
            $table->index(['billing_interval', 'price']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};