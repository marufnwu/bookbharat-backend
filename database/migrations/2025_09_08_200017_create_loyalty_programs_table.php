<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_programs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type')->default('points'); // points, cashback, tier
            $table->decimal('points_per_rupee', 5, 2)->default(1.00); // 1 rupee = X points
            $table->decimal('point_value', 10, 4)->default(0.01); // 1 point = X rupees
            $table->integer('min_redemption_points')->default(100);
            $table->decimal('max_redemption_percentage', 5, 2)->default(50); // max % of order value
            $table->integer('points_expiry_days')->default(365);
            $table->json('earning_rules'); // purchase, review, referral, birthday
            $table->json('tier_thresholds')->nullable(); // bronze, silver, gold, platinum
            $table->boolean('is_active')->default(true);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_programs');
    }
};