<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->onDelete('cascade');
            $table->decimal('old_price', 10, 2);
            $table->decimal('new_price', 10, 2);
            $table->decimal('old_compare_price', 10, 2)->nullable();
            $table->decimal('new_compare_price', 10, 2)->nullable();
            $table->string('change_reason'); // manual, rule, promotion, competitor
            $table->string('change_type'); // increase, decrease
            $table->decimal('change_amount', 10, 2);
            $table->decimal('change_percentage', 5, 2);
            $table->foreignId('changed_by')->nullable()->constrained('users');
            $table->foreignId('pricing_rule_id')->nullable()->constrained('pricing_rules')->onDelete('set null');
            $table->json('context')->nullable(); // additional data about the change
            $table->timestamps();

            $table->index(['product_id', 'variant_id', 'created_at']);
            $table->index(['change_reason', 'created_at']);
            $table->index(['pricing_rule_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_history');
    }
};