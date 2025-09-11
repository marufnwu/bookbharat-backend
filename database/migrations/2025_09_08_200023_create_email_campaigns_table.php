<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // welcome, abandoned_cart, post_purchase, newsletter
            $table->text('description')->nullable();
            $table->json('trigger_conditions'); // event, delay, filters
            $table->json('target_segments')->nullable(); // customer segments
            $table->foreignId('email_template_id')->constrained()->onDelete('cascade');
            $table->string('status')->default('draft'); // draft, active, paused, completed
            $table->datetime('start_date')->nullable();
            $table->datetime('end_date')->nullable();
            $table->integer('sent_count')->default(0);
            $table->integer('delivered_count')->default(0);
            $table->integer('opened_count')->default(0);
            $table->integer('clicked_count')->default(0);
            $table->integer('unsubscribed_count')->default(0);
            $table->decimal('open_rate', 5, 2)->default(0);
            $table->decimal('click_rate', 5, 2)->default(0);
            $table->decimal('conversion_rate', 5, 2)->default(0);
            $table->decimal('revenue_generated', 10, 2)->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index(['status', 'start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_campaigns');
    }
};