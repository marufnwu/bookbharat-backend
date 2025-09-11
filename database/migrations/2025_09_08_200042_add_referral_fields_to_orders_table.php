<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('referral_code_id')->nullable()->constrained('referral_codes')->onDelete('set null');
            $table->decimal('referral_discount', 10, 2)->default(0);
            
            $table->index('referral_code_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['referral_code_id']);
            $table->dropIndex(['referral_code_id']);
            $table->dropColumn(['referral_code_id', 'referral_discount']);
        });
    }
};