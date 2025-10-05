<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_charges', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->enum('type', ['fixed', 'percentage', 'tiered'])->default('fixed');
            $table->decimal('amount', 10, 2)->default(0);
            $table->decimal('percentage', 5, 2)->default(0);
            $table->json('tiers')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->enum('apply_to', ['all', 'cod_only', 'online_only', 'specific_payment_methods', 'conditional'])->default('all');
            $table->json('payment_methods')->nullable();
            $table->json('conditions')->nullable();
            $table->integer('priority')->default(0);
            $table->text('description')->nullable();
            $table->string('display_label');
            $table->boolean('is_taxable')->default(false);
            $table->boolean('apply_after_discount')->default(true);
            $table->boolean('is_refundable')->default(true);
            $table->timestamps();

            $table->index(['is_enabled', 'priority']);
            $table->index('apply_to');
            $table->index('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_charges');
    }
};
