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
        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_weight_slab_id')->constrained('shipping_weight_slabs')->cascadeOnDelete();
            $table->string('zone'); // A, B, C, D, E
            $table->decimal('fwd_rate', 10, 2);
            $table->decimal('rto_rate', 10, 2);
            $table->decimal('aw_rate', 10, 2);
            $table->decimal('cod_charges', 10, 2)->nullable();
            $table->decimal('cod_percentage', 5, 2)->nullable();
            $table->timestamps();
            
            $table->index(['shipping_weight_slab_id', 'zone']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_zones');
    }
};
