<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('recommended_product_id')->constrained('products')->onDelete('cascade');
            $table->string('recommendation_type'); // collaborative, content_based, trending, cross_sell
            $table->decimal('confidence_score', 5, 4); // 0-1 confidence level
            $table->string('algorithm_used'); // cf, content, hybrid, manual
            $table->json('reasoning')->nullable(); // why this was recommended
            $table->integer('click_count')->default(0);
            $table->integer('conversion_count')->default(0);
            $table->decimal('conversion_rate', 5, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'product_id', 'is_active'], 'prod_rec_user_product_active_idx');
            $table->index(['recommendation_type', 'confidence_score'], 'prod_rec_type_confidence_idx');
            $table->index(['recommended_product_id', 'conversion_rate'], 'prod_rec_product_conversion_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_recommendations');
    }
};