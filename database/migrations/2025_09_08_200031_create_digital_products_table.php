<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('digital_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('delivery_type'); // download, license_key, streaming, api_access
            $table->string('file_path')->nullable(); // for downloadable products
            $table->string('file_type')->nullable(); // pdf, video, audio, software
            $table->bigInteger('file_size')->nullable(); // in bytes
            $table->string('license_type')->nullable(); // single, multiple, unlimited
            $table->integer('download_limit')->nullable();
            $table->integer('expiry_days')->nullable(); // link expires after X days
            $table->json('access_details')->nullable(); // API keys, streaming URLs, etc.
            $table->boolean('requires_serial_key')->default(false);
            $table->json('drm_settings')->nullable(); // Digital Rights Management
            $table->timestamps();

            $table->index(['product_id', 'delivery_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('digital_products');
    }
};