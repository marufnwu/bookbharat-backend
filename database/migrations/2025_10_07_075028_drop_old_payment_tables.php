<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CLEANUP: Drop old messy payment tables
     *
     * Removes:
     * - payment_configurations (child table)
     * - payment_settings (parent table)
     *
     * Replaced by: payment_methods (single clean table)
     */
    public function up(): void
    {
        // Drop child table first (foreign key constraint)
        Schema::dropIfExists('payment_configurations');

        // Drop parent table
        Schema::dropIfExists('payment_settings');
    }

    /**
     * Reverse the migrations - recreate old tables for rollback
     */
    public function down(): void
    {
        // Recreate payment_settings table
        Schema::create('payment_settings', function (Blueprint $table) {
            $table->id();
            $table->string('unique_keyword')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('configuration')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_production')->default(false);
            $table->json('supported_currencies')->default('["INR"]');
            $table->json('webhook_config')->nullable();
            $table->integer('priority')->default(0);
            $table->timestamps();
        });

        // Recreate payment_configurations table
        Schema::create('payment_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('payment_method');
            $table->foreignId('payment_setting_id')->nullable()->constrained('payment_settings')->onDelete('cascade');
            $table->boolean('is_enabled')->default(true);
            $table->json('configuration')->nullable();
            $table->json('restrictions')->nullable();
            $table->integer('priority')->default(0);
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }
};
