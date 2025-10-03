<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('carrier_id')->constrained('shipping_carriers');
            $table->string('service_code');
            $table->string('tracking_number')->nullable()->index();
            $table->enum('status', [
                'pending',
                'confirmed',
                'pickup_scheduled',
                'picked_up',
                'in_transit',
                'out_for_delivery',
                'delivered',
                'cancelled',
                'returned',
                'failed'
            ])->default('pending');
            $table->timestamp('pickup_date')->nullable();
            $table->timestamp('expected_delivery_date')->nullable();
            $table->timestamp('actual_delivery_date')->nullable();
            $table->string('label_url')->nullable();
            $table->string('invoice_url')->nullable();
            $table->decimal('shipping_cost', 10, 2)->default(0);
            $table->json('carrier_response')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->string('pod_url')->nullable();
            $table->timestamp('pod_received_at')->nullable();
            $table->decimal('weight', 8, 3)->nullable();
            $table->json('dimensions')->nullable();
            $table->integer('package_count')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['order_id', 'status']);
            $table->index(['carrier_id', 'status']);
            $table->index('expected_delivery_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};