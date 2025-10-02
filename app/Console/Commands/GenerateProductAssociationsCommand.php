<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\GenerateProductAssociations;

class GenerateProductAssociationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'associations:generate
                            {--months=6 : Number of months to look back for orders}
                            {--min-orders=2 : Minimum number of orders to create association}
                            {--async : Run the job asynchronously in the background}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate product associations from order history for "Frequently Bought Together" feature';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $months = (int) $this->option('months');
        $minOrders = (int) $this->option('min-orders');
        $async = $this->option('async');

        $this->info('🔄 Generating product associations from order history...');
        $this->info("📅 Looking back {$months} months");
        $this->info("📊 Minimum orders threshold: {$minOrders}");
        $this->newLine();

        if ($async) {
            // Dispatch job to queue
            GenerateProductAssociations::dispatch($months, $minOrders);

            $this->info('✅ Job dispatched to queue successfully!');
            $this->info('💡 Check logs for progress: tail -f storage/logs/laravel.log');
        } else {
            // Run synchronously
            $this->withProgressBar(1, function () use ($months, $minOrders) {
                $job = new GenerateProductAssociations($months, $minOrders);
                $job->handle();
            });

            $this->newLine(2);
            $this->info('✅ Product associations generated successfully!');
            $this->newLine();

            // Show statistics
            $this->showStatistics();
        }

        return 0;
    }

    /**
     * Show association statistics
     */
    protected function showStatistics()
    {
        $totalAssociations = \App\Models\ProductAssociation::where('association_type', 'bought_together')->count();
        $highConfidence = \App\Models\ProductAssociation::where('association_type', 'bought_together')
            ->where('confidence_score', '>=', 0.5)
            ->count();
        $mediumConfidence = \App\Models\ProductAssociation::where('association_type', 'bought_together')
            ->whereBetween('confidence_score', [0.3, 0.5])
            ->count();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Associations', $totalAssociations],
                ['High Confidence (≥0.5)', $highConfidence],
                ['Medium Confidence (0.3-0.5)', $mediumConfidence],
            ]
        );

        $this->newLine();
        $this->info('💡 Tip: Associations with confidence ≥ 0.3 are used for "Frequently Bought Together"');
    }
}
