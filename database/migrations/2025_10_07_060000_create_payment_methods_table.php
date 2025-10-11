<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CLEAN PAYMENT METHODS TABLE - SINGLE SOURCE OF TRUTH
     *
     * One table to rule them all!
     * - No messy parent-child relationships
     * - No foreign keys
     * - No cascade issues
     * - Simple and clean
     *
     * Each row = one payment method (e.g., razorpay, cod, phonepe, etc.)
     */
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();

            // Unique identifier (e.g., 'razorpay', 'cod', 'phonepe', 'payu')
            $table->string('payment_method', 100)->unique()->comment('Unique identifier for the payment method');

            // Display information
            $table->string('display_name')->comment('User-friendly name shown to customers');
            $table->text('description')->nullable()->comment('Description for customers');

            // MASTER SWITCH - THE ONLY TOGGLE YOU NEED
            $table->boolean('is_enabled')->default(true)->index()->comment('Single source of truth for visibility');

            // Gateway type (for admin UI organization)
            $table->string('gateway_type', 50)->nullable()->index()->comment('razorpay, cashfree, payu, phonepe, cod - for grouping in admin UI');

            // System flags
            $table->boolean('is_system')->default(false)->index()->comment('Predefined gateway - cannot be deleted, only edited');
            $table->boolean('is_default')->default(false)->index()->comment('Default gateway to use for online payments');
            $table->boolean('is_fallback')->default(false)->index()->comment('Fallback gateway if default fails');

            // Gateway credentials (API keys, secrets, merchant IDs, etc.)
            $table->json('credentials')->nullable()->comment('API keys, secrets, merchant IDs - stored as encrypted JSON');

            // Credential schema - defines what fields are required/optional
            $table->json('credential_schema')->nullable()->comment('Schema definition for credentials validation');

            // Method-specific configuration
            $table->json('configuration')->nullable()->comment('advance_payment, service_charges, fees, etc.');

            // Order restrictions
            $table->json('restrictions')->nullable()->comment('min_order_amount, max_order_amount, excluded_categories, etc.');

            // Display priority (higher = shown first)
            $table->integer('priority')->default(0)->index()->comment('Display order in checkout - higher shows first');

            // Environment mode
            $table->boolean('is_production')->default(false)->comment('Test mode (false) or Production mode (true)');

            // Supported currencies
            $table->json('supported_currencies')->nullable()->comment('Array of supported currency codes - defaults to ["INR"]');

            // Webhook configuration
            $table->json('webhook_config')->nullable()->comment('Webhook URLs, secrets, and verification settings');

            $table->timestamps();
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

