<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CLEAN SINGLE TABLE SOLUTION - SINGLE SOURCE OF TRUTH
     *
     * This table replaces both payment_settings and payment_configurations
     * One row = one payment method (e.g., razorpay_upi, cod, cashfree_card)
     *
     * VISIBILITY RULE: is_enabled = THE ONLY SWITCH
     */
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();

            // Unique identifier (e.g., 'razorpay_upi', 'cod', 'cashfree_card')
            $table->string('payment_method')->unique();

            // Display information
            $table->string('display_name');
            $table->text('description')->nullable();

            // SINGLE SOURCE OF TRUTH - THE ONLY TOGGLE
            $table->boolean('is_enabled')->default(true);

            // Gateway grouping (for admin UI organization only)
            $table->string('gateway_type')->nullable()->comment('razorpay, cashfree, payu, cod - for grouping in admin');

            // System flags
            $table->boolean('is_system')->default(false)->comment('Predefined gateway - cannot be deleted, only edited');
            $table->boolean('is_default')->default(false)->comment('Default gateway to use for online payments');
            $table->boolean('is_fallback')->default(false)->comment('Fallback gateway if default fails');

            // Gateway credentials (API keys, secrets, etc.)
            $table->json('credentials')->nullable()->comment('API keys, secrets, merchant IDs - stored as JSON');

            // Credential schema - defines what fields are required/optional
            $table->json('credential_schema')->nullable()->comment('Schema definition for credentials validation');

            // Method-specific configuration
            $table->json('configuration')->nullable()->comment('advance_payment, service_charges, etc.');

            // Order restrictions
            $table->json('restrictions')->nullable()->comment('min_order_amount, max_order_amount, excluded_categories');

            // Display priority (higher = shown first)
            $table->integer('priority')->default(0);

            // Environment
            $table->boolean('is_production')->default(false);

            // Supported currencies
            $table->json('supported_currencies')->default('["INR"]');

            // Webhook configuration
            $table->json('webhook_config')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('is_enabled');
            $table->index('gateway_type');
            $table->index('priority');
            $table->index('is_system');
            $table->index('is_default');
            $table->index('is_fallback');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
