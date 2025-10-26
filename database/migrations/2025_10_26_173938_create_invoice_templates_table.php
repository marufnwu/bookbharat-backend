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
        Schema::create('invoice_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->text('header_html')->nullable();
            $table->text('footer_html')->nullable();
            $table->text('styles_css')->nullable();
            $table->text('thank_you_message')->nullable();
            $table->text('legal_disclaimer')->nullable();
            $table->string('logo_url')->nullable();
            $table->boolean('show_company_address')->default(true);
            $table->boolean('show_gst_number')->default(true);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->json('custom_fields')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_templates');
    }
};
