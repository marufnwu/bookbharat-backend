<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('subscription_plan_id')->constrained()->onDelete('cascade');
            $table->foreignId('order_id')->nullable()->constrained()->onDelete('set null');
            $table->string('status')->default('active'); // active, paused, cancelled, expired, past_due
            $table->datetime('started_at');
            $table->datetime('trial_ends_at')->nullable();
            $table->datetime('current_period_start');
            $table->datetime('current_period_end');
            $table->datetime('cancelled_at')->nullable();
            $table->datetime('ends_at')->nullable();
            $table->integer('billing_cycles_completed')->default(0);
            $table->decimal('current_price', 10, 2);
            $table->string('currency', 3)->default('INR');
            $table->string('payment_method_id')->nullable();
            $table->integer('failed_payment_attempts')->default(0);
            $table->datetime('last_payment_attempt_at')->nullable();
            $table->datetime('next_billing_date');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'next_billing_date']);
            $table->index(['subscription_plan_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_subscriptions');
    }
};