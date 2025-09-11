<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_queries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('session_id');
            $table->string('query');
            $table->string('normalized_query'); // cleaned, lowercased version
            $table->integer('results_count');
            $table->foreignId('clicked_product_id')->nullable()->constrained('products')->onDelete('set null');
            $table->integer('click_position')->nullable(); // position in search results
            $table->json('filters_applied')->nullable(); // category, price range, etc.
            $table->string('sort_order')->nullable();
            $table->decimal('search_time_ms', 8, 2)->nullable(); // search performance
            $table->boolean('converted')->default(false); // resulted in purchase
            $table->timestamps();

            $table->index(['query', 'results_count']);
            $table->index(['user_id', 'created_at']);
            $table->index(['normalized_query', 'created_at']);
            $table->index(['converted', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_queries');
    }
};