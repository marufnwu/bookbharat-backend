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
        Schema::table('payment_configurations', function (Blueprint $table) {
            // Add nullable foreign key column (nullable for backward compatibility during migration)
            $table->unsignedBigInteger('payment_setting_id')->nullable()->after('id');

            // Add foreign key constraint
            $table->foreign('payment_setting_id')
                ->references('id')
                ->on('payment_settings')
                ->onDelete('cascade'); // If gateway deleted, remove config too

            // Add index for performance
            $table->index('payment_setting_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_configurations', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['payment_setting_id']);
            // Drop column
            $table->dropColumn('payment_setting_id');
        });
    }
};
