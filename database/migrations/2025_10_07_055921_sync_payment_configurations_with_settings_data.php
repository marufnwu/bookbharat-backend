<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Link existing payment_configurations to payment_settings
        $configurations = DB::table('payment_configurations')->get();

        foreach ($configurations as $config) {
            // Find matching payment_setting by keyword
            $setting = DB::table('payment_settings')
                ->where('unique_keyword', $config->payment_method)
                ->first();

            if ($setting) {
                // Link configuration to setting
                DB::table('payment_configurations')
                    ->where('id', $config->id)
                    ->update(['payment_setting_id' => $setting->id]);

                echo "✓ Linked {$config->payment_method} configuration to gateway setting\n";
            } else {
                echo "⚠ No gateway setting found for {$config->payment_method}\n";

                // For COD variants and bank_transfer, link to main gateway
                if (str_starts_with($config->payment_method, 'cod')) {
                    $codSetting = DB::table('payment_settings')
                        ->where('unique_keyword', 'cod')
                        ->first();

                    if ($codSetting) {
                        DB::table('payment_configurations')
                            ->where('id', $config->id)
                            ->update(['payment_setting_id' => $codSetting->id]);
                        echo "  → Linked to COD gateway instead\n";
                    }
                }
            }
        }

        // Create payment_settings entries for any missing gateways (like Cashfree)
        $missingConfigs = DB::table('payment_configurations')
            ->whereNull('payment_setting_id')
            ->where('payment_method', '!=', 'bank_transfer')
            ->where('payment_method', 'NOT LIKE', 'cod_%')
            ->get();

        foreach ($missingConfigs as $config) {
            // Create payment_setting for this gateway
            $settingId = DB::table('payment_settings')->insertGetId([
                'unique_keyword' => $config->payment_method,
                'name' => $config->display_name,
                'description' => $config->description,
                'is_active' => $config->is_enabled,
                'is_production' => false,
                'configuration' => json_encode([]),
                'webhook_config' => json_encode([]),
                'supported_currencies' => json_encode(['INR']),
                'priority' => $config->priority ?? 5,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Link configuration
            DB::table('payment_configurations')
                ->where('id', $config->id)
                ->update(['payment_setting_id' => $settingId]);

            echo "✓ Created new gateway setting and linked {$config->payment_method}\n";
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Set all payment_setting_id to null
        DB::table('payment_configurations')->update(['payment_setting_id' => null]);
    }
};
