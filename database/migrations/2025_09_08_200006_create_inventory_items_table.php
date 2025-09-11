<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->onDelete('cascade');
            $table->foreignId('location_id')->constrained('inventory_locations')->onDelete('cascade');
            $table->integer('available_quantity')->default(0);
            $table->integer('reserved_quantity')->default(0);
            $table->integer('on_order_quantity')->default(0);
            $table->integer('allocated_quantity')->default(0);
            $table->integer('damaged_quantity')->default(0);
            $table->integer('reorder_point')->default(0);
            $table->integer('max_stock_level')->default(0);
            $table->decimal('unit_cost', 10, 2)->default(0);
            $table->string('bin_location')->nullable();
            $table->timestamp('last_counted_at')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'variant_id', 'location_id']);
            $table->index(['location_id', 'available_quantity']);
            $table->index(['reorder_point', 'available_quantity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};