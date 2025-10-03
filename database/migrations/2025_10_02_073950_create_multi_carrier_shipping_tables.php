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
        // Main carriers table
        Schema::create('shipping_carriers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique(); // delhivery, bluedart, xpressbees, etc.
            $table->string('name', 100);
            $table->string('display_name', 100);
            $table->string('logo_url')->nullable();
            $table->string('tracking_url')->nullable(); // Public tracking URL pattern

            // API Configuration
            $table->string('api_mode')->default('test'); // test, production
            $table->text('api_endpoint')->nullable();
            $table->text('api_key')->nullable(); // Encrypted
            $table->text('api_secret')->nullable(); // Encrypted
            $table->text('client_name')->nullable();
            $table->text('webhook_url')->nullable();

            // Features & Capabilities
            $table->json('supported_services')->nullable(); // ['express', 'standard', 'priority']
            $table->json('features')->nullable(); // ['cod', 'insurance', 'fragile', 'valuable']
            $table->json('supported_payment_modes')->nullable(); // ['prepaid', 'cod', 'topay']

            // Limits
            $table->decimal('max_weight', 10, 2)->nullable(); // in kg
            $table->decimal('max_length', 10, 2)->nullable(); // in cm
            $table->decimal('max_width', 10, 2)->nullable(); // in cm
            $table->decimal('max_height', 10, 2)->nullable(); // in cm
            $table->decimal('max_volumetric_weight', 10, 2)->nullable();
            $table->integer('volumetric_divisor')->default(5000);

            // Business Rules
            $table->decimal('min_cod_amount', 10, 2)->default(0);
            $table->decimal('max_cod_amount', 10, 2)->nullable();
            $table->decimal('max_insurance_value', 10, 2)->nullable();
            $table->json('prohibited_items')->nullable();
            $table->json('restricted_pincodes')->nullable();

            // Settings
            $table->json('pickup_locations')->nullable(); // Pre-configured pickup addresses
            $table->json('return_address')->nullable();
            $table->boolean('auto_generate_labels')->default(true);
            $table->boolean('supports_reverse_pickup')->default(false);
            $table->boolean('supports_qc_check')->default(false);
            $table->boolean('supports_multi_piece')->default(true);

            // Status & Priority
            $table->boolean('is_active')->default(true);
            $table->boolean('is_primary')->default(false); // Primary carrier for auto-selection
            $table->integer('priority')->default(100); // Higher = preferred
            $table->string('status')->default('active'); // active, inactive, suspended

            // Performance Metrics
            $table->decimal('avg_delivery_rating', 3, 2)->nullable(); // 0-5 scale
            $table->decimal('success_rate', 5, 2)->nullable(); // percentage
            $table->integer('avg_delivery_hours')->nullable();

            // Additional Configuration
            $table->json('config')->nullable(); // Carrier-specific configuration
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('code');
            $table->index('is_active');
            $table->index('priority');
        });

        // Carrier service types (different services per carrier)
        Schema::create('carrier_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carrier_id')->constrained('shipping_carriers')->onDelete('cascade');
            $table->string('service_code', 50); // EXPRESS, SURFACE, etc.
            $table->string('service_name', 100);
            $table->string('display_name', 100);
            $table->text('description')->nullable();

            // Service Characteristics
            $table->string('mode')->default('surface'); // air, surface, rail
            $table->integer('min_delivery_hours')->nullable();
            $table->integer('max_delivery_hours')->nullable();
            $table->json('delivery_days')->nullable(); // ['mon', 'tue', 'wed', 'thu', 'fri', 'sat']
            $table->time('cutoff_time')->nullable(); // Daily cutoff for same-day processing

            // Features
            $table->boolean('supports_cod')->default(true);
            $table->boolean('supports_insurance')->default(true);
            $table->boolean('supports_doorstep_qc')->default(false);
            $table->boolean('supports_doorstep_exchange')->default(false);
            $table->boolean('supports_fragile')->default(true);

            // Pricing Tier
            $table->string('pricing_tier')->default('standard'); // economy, standard, premium
            $table->decimal('base_weight_limit', 10, 2)->default(0.5); // kg

            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(100);

            $table->timestamps();

            $table->unique(['carrier_id', 'service_code']);
            $table->index(['carrier_id', 'is_active']);
        });

        // Carrier rate cards
        Schema::create('carrier_rate_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carrier_service_id')->constrained('carrier_services')->onDelete('cascade');

            // Zone Information
            $table->string('zone_type', 20)->default('standard'); // standard, metro, oda
            $table->string('source_region')->nullable(); // Source city/state
            $table->string('destination_region')->nullable(); // Destination city/state
            $table->string('zone_code', 10)->nullable(); // A, B, C, D, E or custom

            // Weight Slab
            $table->decimal('weight_min', 10, 3); // kg
            $table->decimal('weight_max', 10, 3)->nullable(); // null = no upper limit

            // Base Charges
            $table->decimal('base_rate', 10, 2);
            $table->decimal('additional_per_kg', 10, 2)->default(0);
            $table->decimal('additional_per_500g', 10, 2)->default(0);

            // Additional Charges
            $table->decimal('fuel_surcharge_percent', 5, 2)->default(0);
            $table->decimal('gst_percent', 5, 2)->default(18);
            $table->decimal('handling_charge', 10, 2)->default(0);
            $table->decimal('oda_charge', 10, 2)->default(0); // Out of Delivery Area

            // COD Charges
            $table->decimal('cod_charge_fixed', 10, 2)->default(0);
            $table->decimal('cod_charge_percent', 5, 2)->default(0);
            $table->decimal('min_cod_charge', 10, 2)->default(0);

            // Insurance Charges
            $table->decimal('insurance_percent', 5, 2)->default(0);
            $table->decimal('min_insurance_charge', 10, 2)->default(0);

            // RTO Charges
            $table->decimal('rto_charge', 10, 2)->default(0);
            $table->decimal('rto_percent', 5, 2)->default(0);

            // Validity
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['carrier_service_id', 'zone_code', 'weight_min', 'weight_max'], 'idx_carrier_zone_weight');
            $table->index(['effective_from', 'effective_to']);
        });

        // Carrier pincode serviceability
        Schema::create('carrier_pincode_serviceability', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carrier_id')->constrained('shipping_carriers')->onDelete('cascade');
            $table->string('pincode', 10);

            // Location Details
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('zone', 10)->nullable();
            $table->string('region', 50)->nullable();
            $table->string('area_type', 20)->default('urban'); // urban, rural, remote

            // Service Availability
            $table->boolean('is_serviceable')->default(true);
            $table->boolean('is_cod_available')->default(true);
            $table->boolean('is_prepaid_available')->default(true);
            $table->boolean('is_pickup_available')->default(true);
            $table->boolean('is_reverse_pickup')->default(false);
            $table->boolean('is_oda')->default(false); // Out of Delivery Area

            // Delivery Information
            $table->integer('standard_delivery_days')->nullable();
            $table->integer('express_delivery_days')->nullable();
            $table->time('cutoff_time')->nullable();
            $table->json('delivery_days')->nullable(); // Working days

            // Additional Charges
            $table->decimal('oda_charge', 10, 2)->default(0);
            $table->decimal('area_surcharge', 10, 2)->default(0);

            // Restrictions
            $table->decimal('max_weight', 10, 2)->nullable();
            $table->decimal('max_cod_amount', 10, 2)->nullable();
            $table->json('restricted_items')->nullable();

            $table->timestamp('last_updated')->nullable();
            $table->timestamps();

            $table->unique(['carrier_id', 'pincode'], 'unique_carrier_pincode');
            $table->index(['pincode', 'is_serviceable'], 'idx_pincode_serviceable');
            $table->index(['carrier_id', 'pincode', 'is_cod_available'], 'idx_carrier_pin_cod');
        });

        // Shipping rules engine
        Schema::create('shipping_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->text('description')->nullable();

            // Rule Type
            $table->enum('rule_type', [
                'carrier_selection',
                'rate_adjustment',
                'service_upgrade',
                'free_shipping',
                'carrier_restriction',
                'zone_override'
            ]);

            // Rule Conditions (JSON)
            $table->json('conditions')->nullable();

            // Rule Actions (JSON)
            $table->json('actions');

            // Priority and Status
            $table->integer('priority')->default(100); // Higher priority executed first
            $table->boolean('is_active')->default(true);
            $table->boolean('stop_processing')->default(false); // Stop further rules if matched

            // Validity Period
            $table->datetime('valid_from')->nullable();
            $table->datetime('valid_to')->nullable();

            // Usage Limits
            $table->integer('max_uses')->nullable();
            $table->integer('current_uses')->default(0);
            $table->integer('max_uses_per_customer')->nullable();

            // Tracking
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            $table->timestamps();

            $table->index(['rule_type', 'is_active', 'priority']);
            $table->index(['valid_from', 'valid_to']);
        });

        // Carrier API logs
        Schema::create('carrier_api_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carrier_id')->constrained('shipping_carriers');
            $table->foreignId('order_id')->nullable()->constrained('orders');

            $table->string('api_method', 50); // rate_check, create_order, track, cancel
            $table->string('endpoint');
            $table->json('request_data')->nullable();
            $table->json('response_data')->nullable();
            $table->integer('response_code')->nullable();
            $table->integer('response_time_ms')->nullable();

            $table->string('status', 20); // success, failed, timeout
            $table->text('error_message')->nullable();

            $table->string('tracking_number')->nullable();
            $table->decimal('quoted_price', 10, 2)->nullable();

            $table->timestamps();

            $table->index(['carrier_id', 'created_at']);
            $table->index(['order_id']);
            $table->index(['tracking_number']);
            $table->index(['api_method', 'status']);
        });

        // Rate shopping cache
        Schema::create('shipping_rate_cache', function (Blueprint $table) {
            $table->id();
            $table->string('cache_key', 64)->unique();

            // Request Parameters
            $table->json('request_params');
            $table->string('source_pincode', 10);
            $table->string('destination_pincode', 10);
            $table->decimal('weight', 10, 3);

            // Cached Rates
            $table->json('carrier_rates');

            $table->json('recommended_option')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index('cache_key');
            $table->index('expires_at');
        });

        // Add carrier info to shipments table (only if table exists)
        if (Schema::hasTable('shipments') && !Schema::hasColumn('shipments', 'carrier_id')) {
            Schema::table('shipments', function (Blueprint $table) {
                $table->foreignId('carrier_id')->nullable()->after('order_id')
                    ->constrained('shipping_carriers')->nullOnDelete();
                $table->foreignId('carrier_service_id')->nullable()->after('carrier_id')
                    ->constrained('carrier_services')->nullOnDelete();
                $table->string('carrier_tracking_id')->nullable()->after('tracking_number');
                $table->json('carrier_response')->nullable()->after('carrier_tracking_id');
                $table->json('label_data')->nullable()->after('carrier_response');
                $table->string('pickup_token')->nullable()->after('label_data');
                $table->datetime('pickup_scheduled_at')->nullable()->after('pickup_token');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('shipments') && Schema::hasColumn('shipments', 'carrier_id')) {
            Schema::table('shipments', function (Blueprint $table) {
                $table->dropForeign(['carrier_id']);
                $table->dropForeign(['carrier_service_id']);
                $table->dropColumn([
                    'carrier_id',
                    'carrier_service_id',
                    'carrier_tracking_id',
                    'carrier_response',
                    'label_data',
                    'pickup_token',
                    'pickup_scheduled_at'
                ]);
            });
        }

        Schema::dropIfExists('shipping_rate_cache');
        Schema::dropIfExists('carrier_api_logs');
        Schema::dropIfExists('shipping_rules');
        Schema::dropIfExists('carrier_pincode_serviceability');
        Schema::dropIfExists('carrier_rate_cards');
        Schema::dropIfExists('carrier_services');
        Schema::dropIfExists('shipping_carriers');
    }
};