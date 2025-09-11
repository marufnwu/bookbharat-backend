<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->onDelete('cascade');
            $table->foreignId('location_id')->constrained('inventory_locations')->onDelete('cascade');
            $table->string('alert_type'); // low_stock, out_of_stock, overstock, reorder
            $table->integer('threshold_quantity');
            $table->integer('current_quantity');
            $table->string('status')->default('active'); // active, resolved, dismissed
            $table->timestamp('triggered_at');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'alert_type', 'triggered_at']);
            $table->index(['product_id', 'variant_id', 'location_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_alerts');
    }
};