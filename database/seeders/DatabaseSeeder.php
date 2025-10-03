<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * This seeder automatically detects the environment and runs appropriate seeders.
     * - Production: Essential data only
     * - Development/Local: Full test data
     *
     * Use custom commands for explicit control:
     * - php artisan db:seed-dev (for development)
     * - php artisan db:seed-prod (for production)
     */
    public function run(): void
    {
        $environment = app()->environment();

        $this->command->info("═══════════════════════════════════════");
        $this->command->info("   Environment: " . strtoupper($environment));
        $this->command->info("═══════════════════════════════════════");
        $this->command->newLine();

        if ($environment === 'production') {
            // Production environment - essential data only
            $this->command->warn('Running PRODUCTION seeders (essential data only)...');
            $this->call(ProductionSeeder::class);
        } else {
            // Development/local environment - full test data
            $this->command->info('Running DEVELOPMENT seeders (includes test data)...');
            $this->call(DevelopmentSeeder::class);
        }

        $this->command->newLine();
        $this->command->info('═══════════════════════════════════════');
        $this->command->info('   ✅ Seeding completed successfully!');
        $this->command->info('═══════════════════════════════════════');

        // Show environment-specific tips
        if ($environment === 'production') {
            $this->command->newLine();
            $this->command->warn('⚠️  Production Tips:');
            $this->command->line('   - Change admin password immediately');
            $this->command->line('   - Configure payment gateway API keys');
            $this->command->line('   - Import product catalog');
            $this->command->line('   - Setup SSL and security measures');
        } else {
            $this->command->newLine();
            $this->command->info('📝 Development Tips:');
            $this->command->line('   - Test accounts created with password: "password"');
            $this->command->line('   - Sample products and categories available');
            $this->command->line('   - Test coupons ready for use');
            $this->command->line('   - Run: php artisan serve');
        }
    }
}
