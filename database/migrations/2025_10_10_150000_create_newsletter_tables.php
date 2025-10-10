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
        // Newsletter subscribers table
        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('name')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->json('preferences')->nullable(); // books, offers, news, events
            $table->enum('status', ['active', 'unsubscribed', 'bounced', 'complained'])->default('active');
            $table->string('source')->default('website'); // website, api, import
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->string('confirmation_token')->nullable();
            $table->timestamps();

            $table->index('email');
            $table->index('status');
        });

        // Newsletter settings/configuration table
        Schema::create('newsletter_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('group')->default('general'); // general, email, subscription, smtp
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('newsletter_settings');
        Schema::dropIfExists('newsletter_subscribers');
    }
};

