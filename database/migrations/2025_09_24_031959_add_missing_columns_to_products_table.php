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
            // Check and add missing columns that are expected by the Product model
            if (!Schema::hasColumn('products', 'publisher')) {
                $table->string('publisher')->nullable()->after('author');
            }
            if (!Schema::hasColumn('products', 'isbn')) {
                $table->string('isbn')->nullable()->after('publisher');
            }
            if (!Schema::hasColumn('products', 'language')) {
                $table->string('language')->default('English')->after('isbn');
            }
            if (!Schema::hasColumn('products', 'pages')) {
                $table->integer('pages')->nullable()->after('language');
            }
            if (!Schema::hasColumn('products', 'publication_date')) {
                $table->date('publication_date')->nullable()->after('pages');
            }
            if (!Schema::hasColumn('products', 'low_stock_threshold')) {
                $table->integer('low_stock_threshold')->default(10)->after('stock_quantity');
            }
            if (!Schema::hasColumn('products', 'track_stock')) {
                $table->boolean('track_stock')->default(true)->after('manage_stock');
            }
            if (!Schema::hasColumn('products', 'allow_backorder')) {
                $table->boolean('allow_backorder')->default(false)->after('track_stock');
            }
            if (!Schema::hasColumn('products', 'meta_title')) {
                $table->string('meta_title')->nullable()->after('seo');
            }
            if (!Schema::hasColumn('products', 'meta_description')) {
                $table->text('meta_description')->nullable()->after('meta_title');
            }
            if (!Schema::hasColumn('products', 'meta_keywords')) {
                $table->text('meta_keywords')->nullable()->after('meta_description');
            }
            if (!Schema::hasColumn('products', 'tags')) {
                $table->json('tags')->nullable()->after('meta_keywords');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'publisher',
                'isbn',
                'language',
                'pages',
                'publication_date',
                'low_stock_threshold',
                'track_stock',
                'allow_backorder',
                // 'is_active', // already exists
                'meta_title',
                'meta_description',
                'meta_keywords',
                'tags'
            ]);
        });
    }
};