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
        Schema::create('promotional_banners', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('icon'); // Icon name (e.g., 'truck', 'shield', 'headphones')
            $table->string('icon_color')->default('#3B82F6'); // Hex color for icon
            $table->string('background_color')->nullable(); // Optional background color
            $table->string('link_url')->nullable(); // Optional link
            $table->string('link_text')->nullable(); // Optional link text
            $table->boolean('is_active')->default(true);
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotional_banners');
    }
};

