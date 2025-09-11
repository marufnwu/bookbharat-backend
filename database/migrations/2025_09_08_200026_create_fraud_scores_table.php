<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fraud_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->integer('total_score');
            $table->string('risk_level'); // low, medium, high, critical
            $table->json('triggered_rules'); // array of rule IDs and scores
            $table->json('risk_factors'); // detailed breakdown of risk factors
            $table->string('action_taken'); // approved, flagged, blocked, manual_review
            $table->string('final_decision')->nullable(); // approved, declined after review
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->text('review_notes')->nullable();
            $table->datetime('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['risk_level', 'action_taken']);
            $table->index(['total_score', 'created_at']);
            $table->index(['order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fraud_scores');
    }
};