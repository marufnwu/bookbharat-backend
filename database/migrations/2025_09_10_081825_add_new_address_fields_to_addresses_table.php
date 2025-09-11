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
        Schema::table('addresses', function (Blueprint $table) {
            // Add new address fields matching the frontend requirements
            $table->string('whatsapp_number')->nullable()->after('phone');
            $table->string('village_city_area')->nullable()->after('address_line_2');
            $table->string('house_number')->nullable()->after('village_city_area');
            $table->string('landmark')->nullable()->after('house_number');
            $table->string('pincode', 6)->nullable()->after('postal_code'); // Use pincode instead of postal_code
            $table->string('zila')->nullable()->after('city'); // District/Zila
            $table->string('post_name')->nullable()->after('state');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            // Drop the new fields in reverse order
            $table->dropColumn([
                'post_name',
                'zila', 
                'pincode',
                'landmark',
                'house_number',
                'village_city_area',
                'whatsapp_number'
            ]);
        });
    }
};
