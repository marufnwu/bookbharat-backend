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
        Schema::table('persistent_carts', function (Blueprint $table) {
            $table->enum('status', ['new', 'active', 'abandoned', 'recovered', 'expired'])
                ->default('new')
                ->after('recovery_email_count');
            $table->integer('recovery_probability')
                ->default(50)
                ->after('status');
            $table->enum('customer_segment', ['high_value', 'repeat', 'vip', 'regular'])
                ->default('regular')
                ->after('recovery_probability');
            $table->enum('device_type', ['mobile', 'desktop', 'tablet'])
                ->nullable()
                ->after('customer_segment');
            $table->string('source')
                ->nullable()
                ->comment('direct, ad, organic, email, etc.')
                ->after('device_type');

            // Add indexes for better query performance
            $table->index(['status', 'abandoned_at']);
            $table->index(['customer_segment']);
            $table->index(['recovery_probability']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('persistent_carts', function (Blueprint $table) {
            $table->dropIndex(['status', 'abandoned_at']);
            $table->dropIndex(['customer_segment']);
            $table->dropIndex(['recovery_probability']);
            $table->dropColumn(['status', 'recovery_probability', 'customer_segment', 'device_type', 'source']);
        });
    }
};
