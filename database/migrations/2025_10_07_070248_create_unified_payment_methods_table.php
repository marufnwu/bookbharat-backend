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
        Schema::create('payment_methods_unified', function (Blueprint $table) {
            $table->id();

            // Basic identification
            $table->string('code', 50)->unique()->comment('Unique identifier: razorpay, cod, payu, etc.');
            $table->string('name', 255)->comment('Display name for admin');
            $table->text('description')->nullable()->comment('Description for admin');

            // Type and categorization
            $table->enum('type', ['online', 'cod', 'wallet', 'bank_transfer', 'upi'])->default('online');

            // MASTER SWITCH - Single source of truth
            $table->boolean('is_enabled')->default(false)->comment('Controls both admin visibility AND customer visibility');
            $table->boolean('is_production')->default(false)->comment('Test mode or production mode');

            // Display order
            $table->integer('priority')->default(10)->comment('Display order (higher = shows first)');

            // API Configuration (for online gateways)
            $table->json('api_config')->nullable()->comment('API keys, secrets, merchant IDs, etc.');
            $table->json('webhook_config')->nullable()->comment('Webhook URLs and verification settings');

            // Display configuration (for customer checkout)
            $table->json('display_config')->nullable()->comment('Customer-facing name, description, icon');

            // Business rules
            $table->json('restrictions')->nullable()->comment('Min/max order amounts, excluded categories, etc.');
            $table->json('charges')->nullable()->comment('Service charges, advance payment config, etc.');

            // Supported features
            $table->json('supported_currencies')->nullable()->comment('Array of currency codes: ["INR", "USD"]');
            $table->json('supported_features')->nullable()->comment('Refunds, partial payments, etc.');

            // Metadata
            $table->string('provider', 100)->nullable()->comment('Razorpay, PayU, Cashfree, etc.');
            $table->string('icon', 255)->nullable()->comment('Icon/logo filename or emoji');
            $table->string('documentation_url', 500)->nullable();

            $table->timestamps();

            // Indexes
            $table->index('is_enabled');
            $table->index('type');
            $table->index('priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_methods_unified');
    }
};
