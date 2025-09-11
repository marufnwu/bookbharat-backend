<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number')->unique();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('return_type')->default('refund'); // refund, exchange, store_credit
            $table->string('status')->default('requested'); // requested, approved, rejected, shipped, received, processed, completed
            $table->string('reason'); // defective, wrong_item, not_as_described, changed_mind
            $table->text('description')->nullable();
            $table->json('items'); // array of order items being returned
            $table->decimal('refund_amount', 10, 2)->default(0);
            $table->decimal('shipping_cost_refund', 10, 2)->default(0);
            $table->string('refund_method')->nullable(); // original_payment, store_credit, bank_transfer
            $table->string('return_shipping_method')->nullable();
            $table->string('return_tracking_number')->nullable();
            $table->datetime('requested_at');
            $table->datetime('approved_at')->nullable();
            $table->datetime('shipped_at')->nullable();
            $table->datetime('received_at')->nullable();
            $table->datetime('processed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->foreignId('processed_by')->nullable()->constrained('users');
            $table->text('admin_notes')->nullable();
            $table->json('quality_check_results')->nullable();
            $table->json('images')->nullable(); // return condition photos
            $table->timestamps();

            $table->index(['status', 'requested_at']);
            $table->index(['user_id', 'status']);
            $table->index(['order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('returns');
    }
};