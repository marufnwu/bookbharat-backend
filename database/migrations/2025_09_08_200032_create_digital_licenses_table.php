<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('digital_licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('order_item_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('license_key')->unique();
            $table->string('download_token')->unique();
            $table->string('status')->default('active'); // active, suspended, expired, revoked
            $table->integer('download_count')->default(0);
            $table->integer('max_downloads')->nullable();
            $table->datetime('first_downloaded_at')->nullable();
            $table->datetime('last_downloaded_at')->nullable();
            $table->datetime('expires_at')->nullable();
            $table->json('access_log')->nullable(); // IP addresses, devices, etc.
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['license_key', 'status']);
            $table->index(['expires_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('digital_licenses');
    }
};