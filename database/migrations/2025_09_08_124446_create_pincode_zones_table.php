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
        Schema::create('pincode_zones', function (Blueprint $table) {
            $table->id();
            $table->string('pincode', 6);
            $table->string('zone'); // A, B, C, D, E
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('region')->nullable();
            $table->boolean('is_metro')->default(false);
            $table->boolean('is_remote')->default(false);
            $table->boolean('cod_available')->default(true);
            $table->integer('expected_delivery_days')->default(3);
            $table->decimal('zone_multiplier', 5, 2)->default(1.00); // Zone-specific multiplier
            $table->timestamps();
            
            $table->unique('pincode');
            $table->index(['zone', 'is_metro']);
            $table->index(['state', 'city']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pincode_zones');
    }
};
