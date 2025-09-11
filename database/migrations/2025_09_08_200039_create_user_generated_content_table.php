<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_generated_content', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('content_type'); // review, photo, video, story, unboxing
            $table->text('content')->nullable(); // text content
            $table->json('media_files')->nullable(); // images, videos
            $table->json('metadata')->nullable(); // hashtags, mentions, location
            $table->string('source_platform')->nullable(); // instagram, facebook, website
            $table->string('external_id')->nullable(); // platform-specific ID
            $table->integer('likes_count')->default(0);
            $table->integer('shares_count')->default(0);
            $table->integer('comments_count')->default(0);
            $table->decimal('rating', 3, 2)->nullable(); // if it's a review
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->boolean('is_featured')->default(false);
            $table->boolean('allow_public_display')->default(true);
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->datetime('approved_at')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'status', 'is_featured']);
            $table->index(['user_id', 'content_type']);
            $table->index(['source_platform', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_generated_content');
    }
};