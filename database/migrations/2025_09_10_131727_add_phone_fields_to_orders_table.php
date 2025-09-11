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
            // Store complete address snapshot for data consistency
            // This ensures order data remains intact even if user edits/deletes addresses
            // billing_address & shipping_address already exist as JSON columns
            // We'll modify them to store complete address data with phone numbers
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // No columns to drop - we're using existing JSON columns
        });
    }
};
