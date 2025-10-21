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
        Schema::table('products', function (Blueprint $table) {
            // Add format field (other book fields already exist)
            if (!Schema::hasColumn('products', 'format')) {
                $table->enum('format', ['Hardcover', 'Paperback', 'Ebook', 'Audiobook'])->nullable()->after('pages');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'format')) {
                $table->dropColumn('format');
            }
        });
    }
};
