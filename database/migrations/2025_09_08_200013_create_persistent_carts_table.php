<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('persistent_carts', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->json('cart_data'); // serialized cart contents
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->integer('items_count')->default(0);
            $table->string('currency', 3)->default('INR');
            $table->timestamp('last_activity')->useCurrent();
            $table->timestamp('expires_at')->useCurrent();
            $table->string('recovery_token')->nullable();
            $table->boolean('is_abandoned')->default(false);
            $table->timestamp('abandoned_at')->nullable();
            $table->integer('recovery_email_count')->default(0);
            $table->timestamp('last_recovery_email_sent')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'last_activity']);
            $table->index(['is_abandoned', 'abandoned_at']);
            $table->index(['expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('persistent_carts');
    }
};