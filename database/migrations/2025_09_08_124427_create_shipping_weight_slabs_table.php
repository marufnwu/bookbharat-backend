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
        Schema::create('shipping_weight_slabs', function (Blueprint $table) {
            $table->id();
            $table->string('courier_name');
            $table->decimal('base_weight', 8, 2);
            $table->timestamps();
            
            $table->index(['courier_name', 'base_weight']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_weight_slabs');
    }
};
