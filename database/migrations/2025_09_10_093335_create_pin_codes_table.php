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
        Schema::create('pin_codes', function (Blueprint $table) {
            $table->id();
            $table->string('circlename')->nullable();
            $table->string('regionname')->nullable();
            $table->string('divisionname')->nullable();
            $table->string('officename')->nullable();
            $table->string('pincode', 20)->nullable()->index();
            $table->string('officetype', 50)->nullable();
            $table->string('delivery', 50)->nullable();
            $table->string('district', 100)->nullable();
            $table->string('statename', 100)->nullable();
            $table->string('city', 100)->nullable()->index();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pin_codes');
    }
};
