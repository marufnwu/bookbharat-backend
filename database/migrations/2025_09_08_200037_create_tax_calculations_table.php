<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_calculations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('tax_rate_id')->constrained()->onDelete('cascade');
            $table->string('tax_type'); // gst, vat, etc.
            $table->string('tax_name'); // CGST 9%, SGST 9%, etc.
            $table->decimal('taxable_amount', 10, 2); // amount on which tax is calculated
            $table->decimal('tax_rate', 5, 4); // 0.0900 for 9%
            $table->decimal('tax_amount', 10, 2); // calculated tax amount
            $table->string('calculation_method')->default('percentage'); // percentage, fixed
            $table->json('breakdown')->nullable(); // detailed tax breakdown
            $table->string('jurisdiction');
            $table->string('region_code');
            $table->timestamps();

            $table->index(['order_id', 'tax_type']);
            $table->index(['tax_rate_id', 'jurisdiction']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_calculations');
    }
};