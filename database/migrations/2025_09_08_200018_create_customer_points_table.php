<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('loyalty_program_id')->constrained()->onDelete('cascade');
            $table->string('transaction_type'); // earned, redeemed, expired, adjusted
            $table->integer('points');
            $table->integer('balance_after');
            $table->string('earning_reason')->nullable(); // purchase, review, referral, birthday
            $table->string('reference_type')->nullable(); // order, review, referral
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('order_amount', 10, 2)->nullable(); // for earning calculations
            $table->date('expires_at')->nullable();
            $table->boolean('is_expired')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'transaction_type', 'created_at']);
            $table->index(['loyalty_program_id', 'created_at']);
            $table->index(['expires_at', 'is_expired']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_points');
    }
};