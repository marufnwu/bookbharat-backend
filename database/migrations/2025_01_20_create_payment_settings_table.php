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
        Schema::create('payment_settings', function (Blueprint $table) {
            $table->id();
            $table->string('unique_keyword')->unique(); // payu, phonepe, razorpay, etc.
            $table->string('name'); // Display name
            $table->text('description')->nullable();
            $table->json('configuration'); // Gateway-specific settings (keys, secrets, etc.)
            $table->boolean('is_active')->default(false);
            $table->boolean('is_production')->default(false);
            $table->json('supported_currencies')->nullable();
            $table->json('webhook_config')->nullable(); // Webhook URLs and settings
            $table->integer('priority')->default(0); // Display order
            $table->timestamps();

            $table->index(['is_active', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_settings');
    }
};