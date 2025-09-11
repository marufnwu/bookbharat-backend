<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\OrderAutomationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessOrderWorkflow implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $orderId;
    protected $newStatus;
    protected $notes;
    protected $attempts = 3;

    public function __construct($orderId, $newStatus, $notes = null)
    {
        $this->orderId = $orderId;
        $this->newStatus = $newStatus;
        $this->notes = $notes;
    }

    public function handle(OrderAutomationService $automationService): void
    {
        $order = Order::find($this->orderId);
        
        if (!$order) {
            Log::warning("Order workflow processing skipped - order not found", [
                'order_id' => $this->orderId
            ]);
            return;
        }

        // Check if order is still in expected state
        if ($this->shouldSkipTransition($order)) {
            Log::info("Order workflow transition skipped - order state changed", [
                'order_id' => $this->orderId,
                'current_status' => $order->status,
                'requested_status' => $this->newStatus
            ]);
            return;
        }

        try {
            // Process the state transition
            $workflow = $automationService->processOrderStateTransition(
                $order,
                $this->newStatus,
                null, // system user
                $this->notes ?: "Automated transition to {$this->newStatus}"
            );

            Log::info("Order workflow processed successfully", [
                'order_id' => $this->orderId,
                'from_status' => $workflow->previous_status,
                'to_status' => $workflow->current_status,
                'workflow_id' => $workflow->id
            ]);

            // Trigger additional actions based on new status
            $this->handleStatusSpecificActions($order, $this->newStatus);

        } catch (\Exception $e) {
            Log::error("Failed to process order workflow", [
                'order_id' => $this->orderId,
                'new_status' => $this->newStatus,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    protected function shouldSkipTransition($order)
    {
        // Skip if order is already in the target status or beyond
        $statusHierarchy = [
            'pending' => 0,
            'confirmed' => 1,
            'processing' => 2,
            'shipped' => 3,
            'delivered' => 4,
            'completed' => 5,
            'cancelled' => 6,
            'refunded' => 7,
        ];

        $currentLevel = $statusHierarchy[$order->status] ?? -1;
        $targetLevel = $statusHierarchy[$this->newStatus] ?? -1;

        // Skip if current status is higher than target (order already progressed)
        if ($currentLevel >= $targetLevel && !in_array($order->status, ['pending', 'confirmed'])) {
            return true;
        }

        // Skip cancelled transitions if order payment is successful
        if ($this->newStatus === 'cancelled' && $order->payment_status === 'paid') {
            return true;
        }

        return false;
    }

    protected function handleStatusSpecificActions($order, $status)
    {
        switch ($status) {
            case 'confirmed':
                // Allocate inventory
                $this->allocateInventory($order);
                break;

            case 'cancelled':
                // Release reserved inventory
                $this->releaseInventory($order);
                break;

            case 'shipped':
                // Update tracking information
                $this->updateShippingInfo($order);
                break;

            case 'delivered':
                // Award loyalty points
                $this->awardLoyaltyPoints($order);
                break;

            case 'completed':
                // Generate analytics events
                $this->generateCompletionAnalytics($order);
                break;
        }
    }

    protected function allocateInventory($order)
    {
        foreach ($order->items as $item) {
            if ($item->variant_id) {
                $variant = $item->variant;
                if ($variant) {
                    $variant->allocateStock($item->quantity);
                }
            }
        }
        
        Log::info("Inventory allocated for order", ['order_id' => $order->id]);
    }

    protected function releaseInventory($order)
    {
        foreach ($order->items as $item) {
            if ($item->variant_id) {
                $variant = $item->variant;
                if ($variant) {
                    $variant->releaseStock($item->quantity);
                }
            }
        }
        
        Log::info("Inventory released for cancelled order", ['order_id' => $order->id]);
    }

    protected function updateShippingInfo($order)
    {
        // This would integrate with shipping providers to get tracking info
        Log::info("Shipping information updated", ['order_id' => $order->id]);
    }

    protected function awardLoyaltyPoints($order)
    {
        if ($order->user_id) {
            \App\Jobs\AwardLoyaltyPoints::dispatch(
                $order->user_id,
                'purchase',
                'order',
                $order->id,
                $order->total_amount
            );
        }
    }

    protected function generateCompletionAnalytics($order)
    {
        // Generate analytics events for completed order
        \App\Jobs\RecordAnalyticsEvent::dispatch([
            'event_type' => 'order_completed',
            'user_id' => $order->user_id,
            'entity_type' => 'order',
            'entity_id' => $order->id,
            'properties' => [
                'order_value' => $order->total_amount,
                'item_count' => $order->items->count(),
                'payment_method' => $order->payment_method,
                'processing_time' => $order->created_at->diffInHours(now()),
            ]
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Order workflow processing job failed", [
            'order_id' => $this->orderId,
            'new_status' => $this->newStatus,
            'error' => $exception->getMessage()
        ]);
    }
}