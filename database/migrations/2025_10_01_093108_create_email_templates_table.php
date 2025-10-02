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
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->comment('Template identifier (e.g., order_confirmation, welcome_email)');
            $table->string('subject');
            $table->text('content')->comment('HTML content with variables like {{customer_name}}');
            $table->json('variables')->nullable()->comment('Available variables for this template');
            $table->json('styles')->nullable()->comment('Custom CSS styles');
            $table->string('category')->default('general')->comment('Template category (order, user, marketing, etc.)');
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->string('from_name')->nullable();
            $table->string('from_email')->nullable();
            $table->string('reply_to')->nullable();
            $table->json('cc')->nullable()->comment('CC recipients');
            $table->json('bcc')->nullable()->comment('BCC recipients');
            $table->integer('version')->default(1);
            $table->timestamps();

            $table->index(['name', 'is_active']);
            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};