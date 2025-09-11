<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->decimal('lifetime_value', 10, 2)->default(0);
            $table->decimal('average_order_value', 10, 2)->default(0);
            $table->integer('total_orders')->default(0);
            $table->integer('total_items_purchased')->default(0);
            $table->decimal('total_spent', 10, 2)->default(0);
            $table->integer('days_since_first_order')->default(0);
            $table->integer('days_since_last_order')->default(0);
            $table->decimal('purchase_frequency', 8, 2)->default(0); // orders per month
            $table->string('favorite_category')->nullable();
            $table->string('favorite_brand')->nullable();
            $table->json('purchase_patterns')->nullable(); // time of day, day of week
            $table->decimal('churn_probability', 5, 4)->default(0); // 0-1 score
            $table->string('customer_segment')->nullable();
            $table->string('lifecycle_stage')->nullable(); // new, active, at_risk, churned
            $table->json('preferences')->nullable(); // color, size, price range
            $table->timestamp('calculated_at');
            $table->timestamps();

            $table->unique('user_id');
            $table->index(['lifetime_value', 'calculated_at']);
            $table->index(['customer_segment', 'lifecycle_stage']);
            $table->index(['churn_probability', 'calculated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_analytics');
    }
};