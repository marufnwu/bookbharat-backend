<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fraud_rules', function (Blueprint $table) {
            $table->id();
            $table->string('rule_name');
            $table->text('description')->nullable();
            $table->string('rule_type'); // velocity, amount, geographic, device
            $table->json('conditions'); // rule conditions and thresholds
            $table->integer('risk_score'); // points to add when triggered
            $table->string('action'); // flag, block, review, decline
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0);
            $table->integer('triggered_count')->default(0);
            $table->decimal('accuracy_rate', 5, 2)->default(0); // true positive rate
            $table->timestamps();

            $table->index(['is_active', 'priority']);
            $table->index(['rule_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fraud_rules');
    }
};