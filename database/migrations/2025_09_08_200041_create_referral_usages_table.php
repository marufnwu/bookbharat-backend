<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_code_id')->constrained()->onDelete('cascade');
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // user who used the code
            $table->decimal('discount_amount', 10, 2);
            $table->decimal('commission_amount', 10, 2);
            $table->decimal('order_total', 10, 2);
            $table->json('metadata')->nullable(); // additional tracking data
            $table->timestamps();

            $table->index(['referral_code_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_usages');
    }
};