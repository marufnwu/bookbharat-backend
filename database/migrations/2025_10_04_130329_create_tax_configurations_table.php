<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // GST, IGST, CGST+SGST, VAT
            $table->string('code')->unique(); // gst, igst, vat
            $table->enum('tax_type', ['gst', 'igst', 'cgst_sgst', 'vat', 'sales_tax', 'custom'])->default('gst');
            $table->decimal('rate', 5, 2); // 18.00 for 18%
            $table->boolean('is_enabled')->default(true);
            $table->enum('apply_on', ['subtotal', 'subtotal_with_charges', 'subtotal_with_shipping'])->default('subtotal');
            $table->json('conditions')->nullable(); // State-based, category-based conditions
            $table->boolean('is_inclusive')->default(false); // Price includes tax or tax added on top
            $table->integer('priority')->default(0);
            $table->text('description')->nullable();
            $table->string('display_label'); // What customers see
            $table->timestamps();

            $table->index(['is_enabled', 'priority']);
            $table->index('tax_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_configurations');
    }
};
