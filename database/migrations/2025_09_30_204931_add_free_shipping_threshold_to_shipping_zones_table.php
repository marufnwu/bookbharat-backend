<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shipping_zones', function (Blueprint $table) {
            $table->decimal('free_shipping_threshold', 10, 2)->nullable()->after('cod_percentage');
        });

        // Set default thresholds for each zone
        DB::table('shipping_zones')->where('zone', 'A')->update(['free_shipping_threshold' => 499]);
        DB::table('shipping_zones')->where('zone', 'B')->update(['free_shipping_threshold' => 699]);
        DB::table('shipping_zones')->where('zone', 'C')->update(['free_shipping_threshold' => 999]);
        DB::table('shipping_zones')->where('zone', 'D')->update(['free_shipping_threshold' => 1499]);
        DB::table('shipping_zones')->where('zone', 'E')->update(['free_shipping_threshold' => 2499]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipping_zones', function (Blueprint $table) {
            $table->dropColumn('free_shipping_threshold');
        });
    }
};
