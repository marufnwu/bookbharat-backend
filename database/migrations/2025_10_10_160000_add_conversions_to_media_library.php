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
        Schema::table('media_library', function (Blueprint $table) {
            $table->json('conversions')->nullable()->after('metadata'); // Store optimized versions (thumbnails, webp, etc.)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('media_library', function (Blueprint $table) {
            $table->dropColumn('conversions');
        });
    }
};

