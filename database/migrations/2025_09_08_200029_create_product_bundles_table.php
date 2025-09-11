<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_bundles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('bundle_type')->default('fixed'); // fixed, dynamic, build_your_own
            $table->decimal('bundle_price', 10, 2)->nullable();
            $table->string('discount_type')->default('percentage'); // percentage, fixed
            $table->decimal('discount_value', 10, 2)->default(0);
            $table->integer('min_items')->default(1);
            $table->integer('max_items')->nullable();
            $table->json('required_categories')->nullable(); // for dynamic bundles
            $table->boolean('is_active')->default(true);
            $table->datetime('start_date')->nullable();
            $table->datetime('end_date')->nullable();
            $table->string('image')->nullable();
            $table->json('seo')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'bundle_type']);
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_bundles');
    }
};