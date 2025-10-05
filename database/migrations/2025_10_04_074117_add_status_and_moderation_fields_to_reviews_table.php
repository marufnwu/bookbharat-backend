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
        Schema::table('reviews', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('is_approved');
            $table->boolean('is_reported')->default(false)->after('status');
            $table->integer('report_count')->default(0)->after('is_reported');
            $table->text('rejection_reason')->nullable()->after('report_count');
            $table->foreignId('moderated_by')->nullable()->constrained('users')->onDelete('set null')->after('rejection_reason');
            $table->timestamp('moderated_at')->nullable()->after('moderated_by');
            $table->json('images')->nullable()->after('moderated_at');

            $table->index('status');
            $table->index('is_reported');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn([
                'status',
                'is_reported',
                'report_count',
                'rejection_reason',
                'moderated_by',
                'moderated_at',
                'images'
            ]);
        });
    }
};
