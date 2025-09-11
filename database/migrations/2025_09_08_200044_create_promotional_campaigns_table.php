<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotional_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->enum('type', ['flash_sale', 'seasonal_offer', 'clearance', 'bundle_deal', 'loyalty_bonus', 'referral_bonus']);
            $table->enum('status', ['draft', 'scheduled', 'active', 'paused', 'ended'])->default('draft');
            $table->datetime('starts_at');
            $table->datetime('ends_at');
            $table->json('campaign_rules'); // flexible rules configuration
            $table->json('target_audience')->nullable(); // customer segments, locations, etc.
            $table->json('banner_config')->nullable(); // banner images, colors, text
            $table->json('email_config')->nullable(); // email templates, subject lines
            $table->json('notification_config')->nullable(); // push notification settings
            $table->decimal('budget_limit', 12, 2)->nullable(); // campaign budget cap
            $table->decimal('current_spend', 12, 2)->default(0);
            $table->integer('target_participants')->nullable();
            $table->integer('actual_participants')->default(0);
            $table->decimal('target_revenue', 12, 2)->nullable();
            $table->decimal('actual_revenue', 12, 2)->default(0);
            $table->integer('priority')->default(0); // for campaign ordering/conflicts
            $table->boolean('auto_apply')->default(false); // automatically apply to eligible users
            $table->json('analytics_config')->nullable(); // tracking parameters
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['status', 'starts_at', 'ends_at']);
            $table->index(['type', 'status']);
            $table->index('slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotional_campaigns');
    }
};