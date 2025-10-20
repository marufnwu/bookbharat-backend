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
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->string('event_type')->unique(); // order_placed, order_shipped, etc.
            $table->json('channels')->nullable(); // ['email', 'sms', 'whatsapp']
            $table->boolean('enabled')->default(true);
            
            // SMS Gateway Configuration
            $table->text('sms_gateway_url')->nullable();
            $table->text('sms_api_key')->nullable(); // Encrypted
            $table->string('sms_sender_id')->nullable();
            $table->string('sms_request_format')->default('json'); // json or form
            
            // WhatsApp Business API Configuration
            $table->text('whatsapp_api_url')->nullable();
            $table->text('whatsapp_access_token')->nullable(); // Encrypted
            $table->string('whatsapp_phone_number_id')->nullable();
            $table->string('whatsapp_business_account_id')->nullable();
            $table->json('whatsapp_templates')->nullable(); // Template mappings
            
            // Email Configuration (optional override)
            $table->string('email_from')->nullable();
            $table->string('email_from_name')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('event_type');
            $table->index('enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_settings');
    }
};
