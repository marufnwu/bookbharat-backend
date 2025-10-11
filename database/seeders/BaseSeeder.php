<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Base Seeder with common utilities and progress tracking
 *
 * Provides helper methods for all seeders:
 * - Progress tracking with visual feedback
 * - Safe execution with error handling
 * - Common database operations
 * - Logging and debugging utilities
 */
abstract class BaseSeeder extends Seeder
{
    /**
     * Progress tracking for current operation
     */
    protected array $progress = [];

    /**
     * Error log for tracking issues
     */
    protected array $errorLog = [];

    /**
     * Start progress tracking for a phase
     */
    protected function startProgress(string $name, int $total): void
    {
        $this->progress[$name] = [
            'current' => 0,
            'total' => $total,
            'start_time' => microtime(true),
        ];

        $this->command->info("┌─ {$name}");
    }

    /**
     * Update progress for a phase
     */
    protected function updateProgress(string $name): void
    {
        if (!isset($this->progress[$name])) {
            return;
        }

        $this->progress[$name]['current']++;
        $current = $this->progress[$name]['current'];
        $total = $this->progress[$name]['total'];

        $percentage = $total > 0 ? round(($current / $total) * 100) : 100;
        $this->command->line("├─ Progress: {$current}/{$total} ({$percentage}%)");
    }

    /**
     * Complete progress for a phase
     */
    protected function completeProgress(string $name): void
    {
        if (!isset($this->progress[$name])) {
            return;
        }

        $elapsed = round(microtime(true) - $this->progress[$name]['start_time'], 2);
        $this->command->info("└─ ✅ {$name} completed in {$elapsed}s");
        unset($this->progress[$name]);
    }

    /**
     * Safely execute a callback with error handling
     */
    protected function safeExecute(callable $callback, string $context, string $errorMessage = null): mixed
    {
        try {
            return $callback();
        } catch (\Exception $e) {
            $message = $errorMessage ?? "Error in {$context}";
            $this->logError($context, $message . ': ' . $e->getMessage());
            $this->command->warn("⚠️  {$message}");
            return null;
        }
    }

    /**
     * Log an error for later review
     */
    protected function logError(string $context, string $message): void
    {
        $this->errorLog[] = [
            'context' => $context,
            'message' => $message,
            'timestamp' => now()->toDateTimeString(),
        ];
    }

    /**
     * Create or update a model record
     */
    protected function createOrUpdate(string $model, array $attributes, array $values): ?object
    {
        try {
            return $model::updateOrCreate($attributes, $values);
        } catch (\Exception $e) {
            $this->logError(
                "Create/Update {$model}",
                "Failed to create/update: " . $e->getMessage()
            );
            return null;
        }
    }

    /**
     * Bulk insert records with error handling
     */
    protected function bulkInsert(string $table, array $records): bool
    {
        try {
            DB::table($table)->insert($records);
            return true;
        } catch (\Exception $e) {
            $this->logError(
                "Bulk Insert {$table}",
                "Failed to insert records: " . $e->getMessage()
            );
            return false;
        }
    }

    /**
     * Show error log if any errors occurred
     */
    protected function showErrorLog(): void
    {
        if (empty($this->errorLog)) {
            return;
        }

        $this->command->newLine();
        $this->command->warn('⚠️  Errors encountered during seeding:');
        $this->command->warn('═══════════════════════════════════════');

        foreach ($this->errorLog as $error) {
            $this->command->warn("[{$error['context']}] {$error['message']}");
        }

        $this->command->warn('═══════════════════════════════════════');
        $this->command->warn('Total errors: ' . count($this->errorLog));
    }

    /**
     * Check if a table exists
     */
    protected function tableExists(string $table): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable($table);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Truncate a table safely
     */
    protected function truncateTable(string $table): bool
    {
        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            DB::table($table)->truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            return true;
        } catch (\Exception $e) {
            $this->logError("Truncate {$table}", $e->getMessage());
            return false;
        }
    }

    /**
     * Show seeding statistics
     */
    protected function showStatistics(array $counts): void
    {
        $this->command->newLine();
        $this->command->info('📊 Seeding Statistics:');
        $this->command->info('═══════════════════════════════════════');

        foreach ($counts as $label => $count) {
            $this->command->line("   {$label}: {$count}");
        }

        $this->command->info('═══════════════════════════════════════');
    }
}

