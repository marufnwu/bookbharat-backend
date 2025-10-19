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
        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreignId('bundle_variant_id')
                ->nullable()
                ->after('product_id')
                ->constrained('product_bundle_variants')
                ->onDelete('cascade');

            $table->index(['product_id', 'bundle_variant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropForeign(['bundle_variant_id']);
            $table->dropIndex(['product_id', 'bundle_variant_id']);
            $table->dropColumn('bundle_variant_id');
        });
    }
};

