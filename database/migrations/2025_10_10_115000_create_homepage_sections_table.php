<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('homepage_sections', function (Blueprint $table) {
            $table->id();
            $table->string('section_id')->unique()->comment('Unique identifier for the section');
            $table->string('section_type')->comment('Type of section: hero, featured-products, categories, etc.');
            $table->string('title')->comment('Display title for the section');
            $table->string('subtitle')->nullable()->comment('Display subtitle for the section');
            $table->boolean('enabled')->default(true)->index()->comment('Whether this section is enabled');
            $table->integer('order')->default(0)->index()->comment('Display order on homepage');
            $table->json('settings')->nullable()->comment('Section-specific settings');
            $table->json('styles')->nullable()->comment('Section-specific styling');
            $table->timestamps();

            // Indexes for performance
            $table->index(['enabled', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('homepage_sections');
    }
};

