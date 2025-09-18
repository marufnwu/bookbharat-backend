<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('discount_percentage', 5, 2)->default(0);
            $table->string('pricing_type')->default('percentage'); // percentage, fixed, tier
            $table->json('conditions')->nullable(); // order count, total spent, etc.
            $table->json('benefits')->nullable(); // free shipping, priority support, etc.
            $table->boolean('is_active')->default(true);
            $table->boolean('is_automatic')->default(false); // auto-assign based on conditions
            $table->integer('priority')->default(0); // higher priority wins
            $table->timestamps();

            $table->index(['is_active', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_groups');
    }
};