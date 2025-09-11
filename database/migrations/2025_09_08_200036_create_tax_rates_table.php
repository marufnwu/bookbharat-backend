<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // GST 18%, VAT 20%, etc.
            $table->string('tax_type'); // gst, vat, sales_tax, excise
            $table->string('jurisdiction'); // country, state, city
            $table->string('region_code'); // IN, IN-MH, US-CA, etc.
            $table->decimal('rate', 5, 4); // 0.1800 for 18%
            $table->boolean('is_compound')->default(false); // tax on tax
            $table->boolean('is_inclusive')->default(false); // included in price
            $table->json('applicable_categories')->nullable(); // which product categories
            $table->decimal('min_amount', 10, 2)->nullable(); // minimum amount for tax
            $table->decimal('max_amount', 10, 2)->nullable(); // maximum taxable amount
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0); // for multiple tax resolution
            $table->json('conditions')->nullable(); // additional conditions
            $table->timestamps();

            $table->index(['region_code', 'is_active', 'effective_from']);
            $table->index(['tax_type', 'jurisdiction']);
            $table->index(['effective_from', 'effective_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
    }
};