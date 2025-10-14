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
        Schema::table('shipments', function (Blueprint $table) {
            if (!Schema::hasColumn('shipments', 'carrier_service_id')) {
                $table->unsignedBigInteger('carrier_service_id')->nullable()->after('carrier_id');
            }
            if (!Schema::hasColumn('shipments', 'carrier_tracking_id')) {
                $table->string('carrier_tracking_id')->nullable()->after('tracking_number');
            }
            if (!Schema::hasColumn('shipments', 'label_data')) {
                $table->json('label_data')->nullable()->after('carrier_response');
            }
            if (!Schema::hasColumn('shipments', 'pickup_token')) {
                $table->string('pickup_token')->nullable()->after('label_data');
            }
            if (!Schema::hasColumn('shipments', 'pickup_scheduled_at')) {
                $table->datetime('pickup_scheduled_at')->nullable()->after('pickup_token');
            }
            if (!Schema::hasColumn('shipments', 'last_tracked_at')) {
                $table->datetime('last_tracked_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn([
                'carrier_service_id',
                'carrier_tracking_id',
                'label_data',
                'pickup_token',
                'pickup_scheduled_at',
                'last_tracked_at',
            ]);
        });
    }
};
