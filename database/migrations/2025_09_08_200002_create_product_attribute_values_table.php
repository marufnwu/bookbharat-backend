<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')->constrained('product_attributes')->onDelete('cascade');
            $table->string('value'); // Red, Large, Cotton
            $table->string('slug');
            $table->string('color_code')->nullable(); // hex color for color attributes
            $table->string('image')->nullable(); // image for swatch
            $table->decimal('price_adjustment', 10, 2)->default(0); // +/- price
            $table->string('price_adjustment_type')->default('fixed'); // fixed, percentage
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['attribute_id', 'is_active', 'sort_order']);
            $table->unique(['attribute_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_attribute_values');
    }
};