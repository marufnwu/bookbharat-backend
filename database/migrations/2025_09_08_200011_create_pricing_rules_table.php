<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('rule_type'); // flash_sale, happy_hour, geographic, competitive
            $table->json('conditions'); // time, location, customer segment, competitor price
            $table->json('actions'); // price adjustment, discount percentage
            $table->boolean('is_active')->default(true);
            $table->boolean('is_global')->default(false); // applies to all products
            $table->json('product_filters')->nullable(); // categories, brands, specific products
            $table->datetime('start_datetime')->nullable();
            $table->datetime('end_datetime')->nullable();
            $table->integer('priority')->default(0);
            $table->integer('usage_limit')->nullable();
            $table->integer('usage_count')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'priority']);
            $table->index(['rule_type', 'is_active']);
            $table->index(['start_datetime', 'end_datetime']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_rules');
    }
};