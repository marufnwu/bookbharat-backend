<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_segments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->json('criteria'); // rules for automatic segmentation
            $table->boolean('is_dynamic')->default(true); // auto-update membership
            $table->boolean('is_active')->default(true);
            $table->integer('customer_count')->default(0);
            $table->datetime('last_calculated_at')->nullable();
            $table->json('metadata')->nullable(); // additional segment data
            $table->timestamps();

            $table->index(['is_active', 'is_dynamic']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_segments');
    }
};