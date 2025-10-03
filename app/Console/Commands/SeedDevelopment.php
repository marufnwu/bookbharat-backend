<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Database\Seeders\DevelopmentSeeder;

class SeedDevelopment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:seed-dev
                            {--fresh : Wipe the database before seeding}
                            {--force : Force the operation to run in production}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed the database with development data (includes test users, sample products, etc.)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Check environment
        if (app()->environment('production') && !$this->option('force')) {
            $this->error('❌ This command is intended for development environment only!');
            $this->error('   You are currently in: ' . app()->environment());
            $this->warn('   Use --force flag to run in production (not recommended)');
            return 1;
        }

        $this->info('════════════════════════════════════════');
        $this->info('   📦 BookBharat Development Seeder');
        $this->info('════════════════════════════════════════');
        $this->newLine();

        // Fresh migration if requested
        if ($this->option('fresh')) {
            if ($this->confirm('⚠️  This will DELETE ALL DATA and rebuild the database. Continue?')) {
                $this->warn('Wiping database...');
                // Use $this->call to preserve output
                $this->call('migrate:fresh');
                $this->info('✅ Database wiped and migrated fresh');
                $this->newLine();
            } else {
                $this->info('Cancelled.');
                return 0;
            }
        }

        // Run development seeder
        $this->info('🌱 Running development seeders...');
        $this->newLine();

        $startTime = microtime(true);

        try {
            // Use $this->call instead of Artisan::call to preserve output
            $this->call('db:seed', [
                '--class' => DevelopmentSeeder::class,
                '--force' => true,
            ]);

            $executionTime = round(microtime(true) - $startTime, 2);

            $this->newLine();
            $this->info('════════════════════════════════════════');
            $this->info('   ✅ Development seeding completed!');
            $this->info("   ⏱️  Execution time: {$executionTime} seconds");
            $this->info('════════════════════════════════════════');
            $this->newLine();

            $this->table(
                ['Type', 'Credentials'],
                [
                    ['Admin', 'admin@example.com / password'],
                    ['Customer', 'customer@example.com / password'],
                    ['Test User', 'test@example.com / password'],
                    ['Demo User', 'demo@example.com / password'],
                ]
            );

            $this->newLine();
            $this->info('🚀 Ready for development!');
            $this->info('   Run: php artisan serve');
            $this->info('   Frontend: npm run dev');

        } catch (\Exception $e) {
            $this->error('❌ Seeding failed: ' . $e->getMessage());
            $this->error('   Check the logs for more details');
            return 1;
        }

        return 0;
    }
}