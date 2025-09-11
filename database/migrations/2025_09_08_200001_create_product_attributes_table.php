<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_attributes', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Size, Color, Material
            $table->string('slug');
            $table->string('type')->default('select'); // select, color, text, number
            $table->boolean('is_required')->default(false);
            $table->boolean('is_variation')->default(true); // affects price/stock
            $table->boolean('is_global')->default(true); // applies to all products
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable(); // color picker, unit, etc.
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_attributes');
    }
};