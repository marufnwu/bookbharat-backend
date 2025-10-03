<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Database\Seeders\ProductionSeeder;

class SeedProduction extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:seed-prod
                            {--fresh : Wipe the database before seeding}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed the database with production data (essential data only, no test data)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('════════════════════════════════════════');
        $this->info('   🚀 BookBharat Production Seeder');
        $this->info('════════════════════════════════════════');
        $this->newLine();

        // Confirm production seeding
        if (!app()->environment('production')) {
            $this->warn('⚠️  You are not in production environment.');
            $this->warn('   Current environment: ' . app()->environment());
            if (!$this->confirm('Do you want to continue with production seeding?')) {
                $this->info('Cancelled.');
                return 0;
            }
        }

        // Fresh migration if requested
        if ($this->option('fresh')) {
            $this->error('════════════════════════════════════════');
            $this->error('   ⚠️  CRITICAL WARNING');
            $this->error('════════════════════════════════════════');
            $this->error('You are about to DELETE ALL DATA in the database!');
            $this->error('This action cannot be undone!');
            $this->newLine();

            // Triple confirmation for production fresh migration
            if (app()->environment('production')) {
                $confirm1 = $this->confirm('Are you ABSOLUTELY sure you want to wipe the PRODUCTION database?');
                if (!$confirm1) {
                    $this->info('Cancelled.');
                    return 0;
                }

                $this->error('⚠️  FINAL WARNING: All customer data, orders, and settings will be PERMANENTLY DELETED!');
                $confirmText = $this->ask('Type "DELETE PRODUCTION DATABASE" to confirm');

                if ($confirmText !== 'DELETE PRODUCTION DATABASE') {
                    $this->info('Cancelled - confirmation text did not match.');
                    return 0;
                }
            } else {
                if (!$this->confirm('This will DELETE ALL DATA and rebuild the database. Continue?')) {
                    $this->info('Cancelled.');
                    return 0;
                }
            }

            $this->warn('Wiping database...');
            // Use $this->call to preserve output
            $this->call('migrate:fresh');
            $this->info('✅ Database wiped and migrated fresh');
            $this->newLine();
        }

        // Run production seeder
        $this->info('🌱 Running production seeders...');
        $this->info('   This will only seed essential data:');
        $this->info('   • Roles & Permissions');
        $this->info('   • Payment Configurations');
        $this->info('   • Shipping Configurations');
        $this->info('   • Admin Settings');
        $this->info('   • Super Admin Account');
        $this->newLine();

        $startTime = microtime(true);

        try {
            // Use $this->call instead of Artisan::call to preserve output
            $this->call('db:seed', [
                '--class' => ProductionSeeder::class,
                '--force' => true,
            ]);

            $executionTime = round(microtime(true) - $startTime, 2);

            $this->newLine();
            $this->info('════════════════════════════════════════');
            $this->info('   ✅ Production seeding completed!');
            $this->info("   ⏱️  Execution time: {$executionTime} seconds");
            $this->info('════════════════════════════════════════');
            $this->newLine();

            // Post-seeding checklist
            $this->warn('📋 POST-DEPLOYMENT CHECKLIST:');
            $this->newLine();

            $checklist = [
                ['Task', 'Status', 'Action Required'],
                ['Super Admin Account', '✅ Created', 'Change default password immediately'],
                ['Payment Gateways', '⚠️  Config Added', 'Update API keys in admin panel'],
                ['Shipping Carriers', '⚠️  Config Added', 'Configure carrier accounts'],
                ['Email Settings', '⚠️  Basic', 'Configure SMTP settings in .env'],
                ['Categories', '❌ None', 'Add product categories via admin'],
                ['Products', '❌ None', 'Import product catalog'],
                ['Pincodes', '⚠️  Limited', 'Import complete pincode database'],
                ['SSL Certificate', '❓ Check', 'Ensure HTTPS is configured'],
                ['Backup System', '❓ Check', 'Setup automated backups'],
                ['Monitoring', '❓ Check', 'Setup error tracking (Sentry, etc.)'],
            ];

            $this->table(['Task', 'Status', 'Action Required'], $checklist);

            $this->newLine();
            $this->info('🔐 Security Reminders:');
            $this->info('   1. Change the super admin password immediately');
            $this->info('   2. Update .env with production values');
            $this->info('   3. Set APP_DEBUG=false in .env');
            $this->info('   4. Configure proper CORS settings');
            $this->info('   5. Setup firewall rules');
            $this->info('   6. Enable rate limiting');
            $this->info('   7. Configure log rotation');
            $this->newLine();

            $adminEmail = env('ADMIN_EMAIL', 'admin@bookbharat.com');
            $this->info("🔑 Super Admin: {$adminEmail}");
            if (!env('ADMIN_PASSWORD')) {
                $this->warn('   Default Password: ChangeMe@123!');
                $this->error('   ⚠️  CHANGE THIS PASSWORD IMMEDIATELY!');
            }

        } catch (\Exception $e) {
            $this->error('❌ Seeding failed: ' . $e->getMessage());
            $this->error('   Check the logs for more details');
            return 1;
        }

        return 0;
    }
}
