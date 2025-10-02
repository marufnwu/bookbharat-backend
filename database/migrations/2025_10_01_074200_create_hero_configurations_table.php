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
        Schema::create('hero_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('variant')->unique();
            $table->string('title');
            $table->text('subtitle')->nullable();
            $table->json('primaryCta')->nullable();
            $table->json('secondaryCta')->nullable();
            $table->json('discountBadge')->nullable();
            $table->json('trustBadges')->nullable();
            $table->string('backgroundImage')->nullable();
            $table->json('testimonials')->nullable();
            $table->json('campaignData')->nullable();
            $table->json('categories')->nullable();
            $table->json('features')->nullable();
            $table->json('stats')->nullable();
            $table->json('featuredProducts')->nullable();
            $table->string('videoUrl')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hero_configurations');
    }
};
