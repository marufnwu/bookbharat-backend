<?php

namespace App\Listeners;

use App\Models\Order;
use App\Models\ProductAssociation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateProductAssociations
{
    /**
     * Handle the order completed event.
     *
     * @param  Order  $order
     * @return void
     */
    public function handle(Order $order)
    {
        // Only process delivered or completed orders
        if (!in_array($order->status, ['delivered', 'completed'])) {
            return;
        }

        try {
            $this->updateAssociations($order);
            $this->updateBundleAnalytics($order);
        } catch (\Exception $e) {
            Log::error('Failed to update product associations: ' . $e->getMessage());
        }
    }

    /**
     * Update product associations based on order items
     */
    protected function updateAssociations(Order $order)
    {
        $productIds = $order->orderItems->pluck('product_id')->toArray();
        
        // Skip if order has only one item
        if (count($productIds) < 2) {
            return;
        }

        // Update associations for each pair of products
        foreach ($productIds as $productId) {
            foreach ($productIds as $associatedId) {
                if ($productId !== $associatedId) {
                    $this->updateOrCreateAssociation($productId, $associatedId);
                }
            }
        }
    }

    /**
     * Update or create a product association
     */
    protected function updateOrCreateAssociation($productId, $associatedId)
    {
        $association = ProductAssociation::firstOrNew([
            'product_id' => $productId,
            'associated_product_id' => $associatedId,
            'association_type' => 'bought_together'
        ]);

        $association->frequency = ($association->frequency ?? 0) + 1;
        $association->last_purchased_together = now();
        $association->save();

        // Update confidence score
        $association->updateConfidenceScore();
    }

    /**
     * Update bundle analytics if products were bought as a bundle
     */
    protected function updateBundleAnalytics(Order $order)
    {
        $productIds = $order->orderItems->pluck('product_id')->sort()->toArray();
        
        if (count($productIds) < 2) {
            return;
        }

        // Create a bundle ID from sorted product IDs
        $bundleId = 'bundle_' . implode('_', $productIds);

        DB::table('bundle_analytics')->updateOrInsert(
            ['bundle_id' => $bundleId],
            [
                'product_ids' => json_encode($productIds),
                'purchases' => DB::raw('purchases + 1'),
                'total_revenue' => DB::raw('total_revenue + ' . $order->total_amount),
                'updated_at' => now()
            ]
        );
    }
}