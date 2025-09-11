<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bundle_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bundle_id')->constrained('product_bundles')->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->onDelete('cascade');
            $table->integer('quantity')->default(1);
            $table->boolean('is_optional')->default(false);
            $table->boolean('is_required')->default(true);
            $table->decimal('individual_price', 10, 2)->nullable(); // override product price
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['bundle_id', 'product_id', 'variant_id']);
            $table->index(['bundle_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bundle_products');
    }
};