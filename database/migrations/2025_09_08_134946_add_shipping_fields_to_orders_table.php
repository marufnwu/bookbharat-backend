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
        Schema::table('orders', function (Blueprint $table) {
            $table->string('shipping_zone', 10)->nullable()->after('shipping_amount');
            $table->foreignId('delivery_option_id')->nullable()->after('shipping_zone')->constrained('delivery_options')->nullOnDelete();
            $table->decimal('insurance_amount', 10, 2)->default(0)->after('delivery_option_id');
            $table->json('shipping_details')->nullable()->after('insurance_amount');
            $table->string('pickup_pincode', 10)->nullable()->after('shipping_details');
            $table->string('delivery_pincode', 10)->nullable()->after('pickup_pincode');
            $table->date('estimated_delivery_date')->nullable()->after('delivery_pincode');
            $table->string('tracking_number', 100)->nullable()->after('estimated_delivery_date');
            $table->string('courier_partner', 100)->nullable()->after('tracking_number');
            
            $table->index('shipping_zone');
            $table->index('tracking_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['shipping_zone']);
            $table->dropIndex(['tracking_number']);
            $table->dropForeign(['delivery_option_id']);
            $table->dropColumn([
                'shipping_zone',
                'delivery_option_id', 
                'insurance_amount',
                'shipping_details',
                'pickup_pincode',
                'delivery_pincode',
                'estimated_delivery_date',
                'tracking_number',
                'courier_partner'
            ]);
        });
    }
};
