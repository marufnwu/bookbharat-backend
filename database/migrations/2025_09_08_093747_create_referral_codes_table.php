<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('code')->unique();
            $table->enum('discount_type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('discount_value', 8, 2)->default(10);
            $table->integer('usage_limit')->nullable();
            $table->integer('usage_count')->default(0);
            $table->decimal('min_order_amount', 10, 2)->default(0);
            $table->datetime('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->decimal('commission_rate', 5, 2)->default(5.0);
            $table->decimal('commission_earned', 10, 2)->default(0);
            $table->decimal('total_revenue', 12, 2)->default(0);
            $table->timestamps();

            $table->index(['code', 'is_active']);
            $table->index(['user_id', 'is_active']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_codes');
    }
};