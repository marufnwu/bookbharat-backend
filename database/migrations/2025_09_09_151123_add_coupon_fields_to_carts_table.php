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
        Schema::table('carts', function (Blueprint $table) {
            $table->string('coupon_code')->nullable()->after('total_items');
            $table->decimal('coupon_discount', 10, 2)->default(0)->after('coupon_code');
            $table->boolean('coupon_free_shipping')->default(false)->after('coupon_discount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropColumn(['coupon_code', 'coupon_discount', 'coupon_free_shipping']);
        });
    }
};
