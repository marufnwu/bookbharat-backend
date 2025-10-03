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
        Schema::table('shipping_carriers', function (Blueprint $table) {
            // Add performance metrics columns
            if (!Schema::hasColumn('shipping_carriers', 'rating')) {
                $table->decimal('rating', 3, 2)->default(4.0)->after('priority')
                    ->comment('Carrier rating out of 5');
            }

            if (!Schema::hasColumn('shipping_carriers', 'success_rate')) {
                $table->decimal('success_rate', 5, 2)->default(95.00)->after('rating')
                    ->comment('Delivery success rate percentage');
            }

            if (!Schema::hasColumn('shipping_carriers', 'average_delivery_time')) {
                $table->decimal('average_delivery_time', 4, 1)->nullable()->after('success_rate')
                    ->comment('Average delivery time in days');
            }

            if (!Schema::hasColumn('shipping_carriers', 'total_shipments')) {
                $table->integer('total_shipments')->default(0)->after('average_delivery_time')
                    ->comment('Total shipments processed');
            }

            if (!Schema::hasColumn('shipping_carriers', 'display_name')) {
                $table->string('display_name')->nullable()->after('name')
                    ->comment('Display name for UI');
            }

            if (!Schema::hasColumn('shipping_carriers', 'logo_url')) {
                $table->string('logo_url')->nullable()->after('display_name')
                    ->comment('URL to carrier logo');
            }

            // Add indexes for performance
            $table->index('rating');
            $table->index('success_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipping_carriers', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex(['rating']);
            $table->dropIndex(['success_rate']);

            // Drop columns
            $table->dropColumn([
                'rating',
                'success_rate',
                'average_delivery_time',
                'total_shipments',
                'display_name',
                'logo_url'
            ]);
        });
    }
};