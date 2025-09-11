<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_behavior', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('session_id');
            $table->string('action'); // view, cart_add, cart_remove, purchase, search
            $table->foreignId('product_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('search_query')->nullable();
            $table->integer('duration_seconds')->nullable(); // time spent on page
            $table->integer('scroll_depth')->nullable(); // percentage scrolled
            $table->json('context')->nullable(); // device, browser, location
            $table->decimal('implicit_rating', 3, 2)->nullable(); // calculated interest score
            $table->timestamps();

            $table->index(['user_id', 'action', 'created_at']);
            $table->index(['session_id', 'created_at']);
            $table->index(['product_id', 'action']);
            $table->index(['search_query', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_behavior');
    }
};