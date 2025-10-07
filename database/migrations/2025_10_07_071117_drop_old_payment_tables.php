<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DROP THE OLD MESSY TABLES - We only use payment_methods_unified now
     */
    public function up(): void
    {
        Schema::dropIfExists('payment_configurations');
        Schema::dropIfExists('payment_settings');

        echo "🗑️ Dropped old payment_configurations table\n";
        echo "🗑️ Dropped old payment_settings table\n";
        echo "✅ CLEAN! Only payment_methods_unified remains!\n";
    }

    /**
     * Reverse (can't restore deleted tables)
     */
    public function down(): void
    {
        // Can't restore deleted tables
    }
};
