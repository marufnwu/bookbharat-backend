<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('business_name');
            $table->string('legal_name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('contact_person');
            $table->string('email')->unique();
            $table->string('phone');
            $table->text('business_address');
            $table->string('city');
            $table->string('state');
            $table->string('postal_code');
            $table->string('country');
            $table->string('tax_id')->nullable(); // GST, VAT, etc.
            $table->string('bank_account_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_ifsc_code')->nullable();
            $table->decimal('commission_rate', 5, 2)->default(10); // platform commission
            $table->string('commission_type')->default('percentage'); // percentage, fixed
            $table->string('status')->default('pending'); // pending, active, suspended, rejected
            $table->decimal('rating', 3, 2)->default(0);
            $table->integer('total_reviews')->default(0);
            $table->integer('total_products')->default(0);
            $table->integer('total_orders')->default(0);
            $table->decimal('total_revenue', 12, 2)->default(0);
            $table->json('settings')->nullable(); // shipping, return policies
            $table->datetime('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['status', 'approved_at']);
            $table->index(['rating', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};