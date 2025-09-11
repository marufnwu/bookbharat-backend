<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('sku')->unique();
            $table->decimal('price', 10, 2);
            $table->decimal('compare_price', 10, 2)->nullable();
            $table->decimal('cost_price', 10, 2)->nullable();
            $table->integer('stock_quantity')->default(0);
            $table->integer('reserved_quantity')->default(0);
            $table->decimal('weight', 8, 2)->nullable();
            $table->string('dimensions')->nullable();
            $table->string('barcode')->nullable();
            $table->string('combination_hash'); // hash of attribute combinations
            $table->boolean('is_active')->default(true);
            $table->boolean('track_quantity')->default(true);
            $table->string('image')->nullable(); // variant-specific image
            $table->json('variant_attributes'); // [{attribute_id: 1, value_id: 5}]
            $table->timestamps();

            $table->index(['product_id', 'is_active']);
            $table->index(['stock_quantity', 'track_quantity']);
            $table->index('combination_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};