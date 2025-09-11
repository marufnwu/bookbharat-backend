<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PinCodeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the path to the SQL file
        $sqlFilePath = database_path('sqls/pin_codes.sql');
        
        if (!file_exists($sqlFilePath)) {
            $this->command->error('SQL file not found at: ' . $sqlFilePath);
            return;
        }

        $this->command->info('Starting pincode data import...');
        
        // Clear existing pin_codes table
        DB::table('pin_codes')->truncate();
        $this->command->info('Cleared existing pincode data.');

        // Read the SQL file
        $sqlContent = file_get_contents($sqlFilePath);
        
        // Extract INSERT statements and execute them
        $this->executeInsertStatements($sqlContent);
        
        $this->command->info('Pincode data import completed successfully!');
    }

    /**
     * Extract and execute INSERT statements from SQL content
     */
    private function executeInsertStatements(string $sqlContent): void
    {
        // Split the content by semicolons to get individual statements
        $statements = explode(';', $sqlContent);
        
        $insertCount = 0;
        $batchSize = 1000; // Process in batches for better performance
        $currentBatch = [];
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            
            // Skip empty statements, comments, and non-INSERT statements
            if (empty($statement) || 
                strpos($statement, '--') === 0 || 
                strpos($statement, '/*') === 0 ||
                !preg_match('/^INSERT INTO.*pin_codes/i', $statement)) {
                continue;
            }

            try {
                // Execute the INSERT statement
                DB::unprepared($statement);
                $insertCount++;
                
                // Show progress every 100 inserts
                if ($insertCount % 100 == 0) {
                    $this->command->info("Processed {$insertCount} INSERT statements...");
                }
                
            } catch (\Exception $e) {
                $this->command->warn("Failed to execute statement (skipping): " . substr($statement, 0, 100) . "...");
                $this->command->warn("Error: " . $e->getMessage());
                continue;
            }
        }
        
        $this->command->info("Total INSERT statements processed: {$insertCount}");
        
        // Get final count
        $totalRecords = DB::table('pin_codes')->count();
        $this->command->info("Total pincode records in database: {$totalRecords}");
    }
}