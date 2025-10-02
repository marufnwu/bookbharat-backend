<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\ProductAssociation;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class GenerateProductAssociations implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 minutes
    public $tries = 3;

    protected $monthsBack;
    protected $minOrders;

    /**
     * Create a new job instance.
     */
    public function __construct($monthsBack = 6, $minOrders = 2)
    {
        $this->monthsBack = $monthsBack;
        $this->minOrders = $minOrders;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $startTime = microtime(true);
        $startDate = Carbon::now()->subMonths($this->monthsBack);

        Log::info('Starting product associations generation', [
            'start_date' => $startDate->toDateString(),
            'min_orders' => $this->minOrders
        ]);

        // Get delivered orders from the specified time period
        $orders = Order::where('status', 'delivered')
            ->where('created_at', '>=', $startDate)
            ->with(['orderItems.product' => function ($query) {
                $query->where('status', 'active');
            }])
            ->get();

        $totalOrders = $orders->count();
        $associationsCreated = 0;
        $associationsUpdated = 0;

        foreach ($orders as $order) {
            $products = $order->orderItems
                ->pluck('product')
                ->filter()
                ->unique('id');

            if ($products->count() < 2) {
                continue; // Need at least 2 products to create associations
            }

            // Create associations for each product pair
            for ($i = 0; $i < $products->count(); $i++) {
                for ($j = $i + 1; $j < $products->count(); $j++) {
                    $productA = $products[$i];
                    $productB = $products[$j];

                    if (!$productA || !$productB) {
                        continue;
                    }

                    // Create bidirectional associations
                    $result1 = $this->updateAssociation(
                        $productA->id,
                        $productB->id,
                        $order->created_at
                    );

                    $result2 = $this->updateAssociation(
                        $productB->id,
                        $productA->id,
                        $order->created_at
                    );

                    if ($result1 === 'created') $associationsCreated++;
                    if ($result1 === 'updated') $associationsUpdated++;
                    if ($result2 === 'created') $associationsCreated++;
                    if ($result2 === 'updated') $associationsUpdated++;
                }
            }
        }

        $executionTime = round(microtime(true) - $startTime, 2);

        Log::info('Product associations generation completed', [
            'total_orders' => $totalOrders,
            'associations_created' => $associationsCreated,
            'associations_updated' => $associationsUpdated,
            'execution_time_seconds' => $executionTime
        ]);
    }

    /**
     * Update or create a product association
     */
    protected function updateAssociation($productId, $associatedProductId, $purchaseDate): string
    {
        $association = ProductAssociation::where('product_id', $productId)
            ->where('associated_product_id', $associatedProductId)
            ->where('association_type', 'bought_together')
            ->first();

        if ($association) {
            // Update existing association
            $association->frequency += 1;
            $association->last_purchased_together = $purchaseDate;
            $association->save();

            // Update confidence score
            $association->updateConfidenceScore();

            return 'updated';
        } else {
            // Create new association
            $association = ProductAssociation::create([
                'product_id' => $productId,
                'associated_product_id' => $associatedProductId,
                'frequency' => 1,
                'confidence_score' => 0.0,
                'association_type' => 'bought_together',
                'last_purchased_together' => $purchaseDate,
            ]);

            // Calculate initial confidence score
            $association->updateConfidenceScore();

            return 'created';
        }
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Product associations generation failed', [
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
