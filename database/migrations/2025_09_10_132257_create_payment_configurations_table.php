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
        Schema::create('payment_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('payment_method'); // cod, razorpay, cashfree, bank_transfer, etc.
            $table->boolean('is_enabled')->default(true);
            $table->json('configuration')->nullable(); // Method-specific configurations
            $table->json('restrictions')->nullable(); // Order value, product restrictions
            $table->integer('priority')->default(0); // Display order
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->timestamps();
            
            $table->unique('payment_method');
            $table->index(['is_enabled', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_configurations');
    }
};
