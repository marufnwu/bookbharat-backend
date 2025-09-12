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
            // Add missing columns if they don't exist
            if (!Schema::hasColumn('products', 'author')) {
                $table->string('author')->nullable()->after('brand');
            }
            if (!Schema::hasColumn('products', 'rating')) {
                $table->decimal('rating', 2, 1)->default(0)->after('status');
            }
            if (!Schema::hasColumn('products', 'is_bestseller')) {
                $table->boolean('is_bestseller')->default(false)->after('is_featured');
            }
            if (!Schema::hasColumn('products', 'sales_count')) {
                $table->unsignedInteger('sales_count')->default(0)->after('stock_quantity');
            }
            
            // Add indexes for performance
            if (!Schema::hasColumn('products', 'author')) {
                $table->index('author');
            }
            if (!Schema::hasColumn('products', 'is_bestseller')) {
                $table->index('is_bestseller');
            }
            if (!Schema::hasColumn('products', 'rating')) {
                $table->index('rating');
            }
            if (!Schema::hasColumn('products', 'sales_count')) {
                $table->index('sales_count');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $columns = ['author', 'rating', 'is_bestseller', 'sales_count'];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};