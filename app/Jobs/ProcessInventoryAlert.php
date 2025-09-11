<?php

namespace App\Jobs;

use App\Models\StockAlert;
use App\Models\InventoryItem;
use App\Models\User;
use App\Notifications\LowStockNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class ProcessInventoryAlert implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $alertId;
    protected $attempts = 3;

    public function __construct($alertId)
    {
        $this->alertId = $alertId;
    }

    public function handle(): void
    {
        $alert = StockAlert::with(['product', 'variant', 'location'])
            ->find($this->alertId);
        
        if (!$alert) {
            Log::warning("Inventory alert processing skipped - alert not found", [
                'alert_id' => $this->alertId
            ]);
            return;
        }

        // Skip if alert is already resolved
        if ($alert->status !== 'active') {
            Log::info("Inventory alert processing skipped - already resolved", [
                'alert_id' => $this->alertId,
                'status' => $alert->status
            ]);
            return;
        }

        try {
            // Check current stock levels
            $currentStock = $this->getCurrentStockLevel($alert);
            
            // Update alert with current stock
            $alert->update(['current_quantity' => $currentStock]);

            // Process based on alert type
            switch ($alert->alert_type) {
                case 'low_stock':
                    $this->handleLowStockAlert($alert, $currentStock);
                    break;
                    
                case 'out_of_stock':
                    $this->handleOutOfStockAlert($alert, $currentStock);
                    break;
                    
                case 'overstock':
                    $this->handleOverstockAlert($alert, $currentStock);
                    break;
                    
                case 'reorder':
                    $this->handleReorderAlert($alert, $currentStock);
                    break;
            }

            Log::info("Inventory alert processed successfully", [
                'alert_id' => $this->alertId,
                'alert_type' => $alert->alert_type,
                'current_stock' => $currentStock
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to process inventory alert", [
                'alert_id' => $this->alertId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    protected function getCurrentStockLevel($alert)
    {
        $inventoryItem = InventoryItem::where('product_id', $alert->product_id)
            ->where('variant_id', $alert->variant_id)
            ->where('location_id', $alert->location_id)
            ->first();

        return $inventoryItem ? $inventoryItem->available_quantity : 0;
    }

    protected function handleLowStockAlert($alert, $currentStock)
    {
        // If stock is now above threshold, resolve the alert
        if ($currentStock > $alert->threshold_quantity) {
            $alert->update([
                'status' => 'resolved',
                'resolved_at' => now(),
                'notes' => 'Stock level restored above threshold'
            ]);
            return;
        }

        // Send notification to inventory managers
        $this->notifyInventoryManagers($alert, 'low_stock');
        
        // Auto-generate purchase order if enabled
        $this->generatePurchaseOrderIfNeeded($alert);
    }

    protected function handleOutOfStockAlert($alert, $currentStock)
    {
        // If stock is restored, resolve the alert
        if ($currentStock > 0) {
            $alert->update([
                'status' => 'resolved',
                'resolved_at' => now(),
                'notes' => 'Stock restored'
            ]);
            return;
        }

        // Send urgent notification
        $this->notifyInventoryManagers($alert, 'out_of_stock');
        
        // Disable product if completely out of stock across all locations
        $this->checkAndDisableProductIfNeeded($alert);
    }

    protected function handleOverstockAlert($alert, $currentStock)
    {
        $maxStockLevel = $this->getMaxStockLevel($alert);
        
        // If stock is now within acceptable range, resolve
        if ($currentStock <= $maxStockLevel) {
            $alert->update([
                'status' => 'resolved',
                'resolved_at' => now(),
                'notes' => 'Stock level normalized'
            ]);
            return;
        }

        // Suggest promotional campaigns or transfers
        $this->suggestOverstockActions($alert);
    }

    protected function handleReorderAlert($alert, $currentStock)
    {
        $reorderPoint = $alert->threshold_quantity;
        
        // If stock is above reorder point, resolve
        if ($currentStock > $reorderPoint) {
            $alert->update([
                'status' => 'resolved',
                'resolved_at' => now(),
                'notes' => 'Stock replenished above reorder point'
            ]);
            return;
        }

        // Generate automatic purchase order
        $this->generateAutomaticPurchaseOrder($alert);
    }

    protected function notifyInventoryManagers($alert, $urgency)
    {
        // Get users with inventory management permissions
        $managers = User::role('inventory_manager')
            ->orWhere(function ($query) {
                $query->role('admin');
            })
            ->get();

        if ($managers->isEmpty()) {
            Log::warning("No inventory managers found to notify", [
                'alert_id' => $alert->id
            ]);
            return;
        }

        $notificationData = [
            'alert' => $alert,
            'urgency' => $urgency,
            'product_name' => $alert->product->name,
            'variant_info' => $alert->variant ? $alert->variant->formatted_attributes : null,
            'location_name' => $alert->location->name,
            'current_stock' => $alert->current_quantity,
            'threshold' => $alert->threshold_quantity,
        ];

        Notification::send($managers, new LowStockNotification($notificationData));
        
        Log::info("Inventory alert notifications sent", [
            'alert_id' => $alert->id,
            'managers_notified' => $managers->count(),
            'urgency' => $urgency
        ]);
    }

    protected function generatePurchaseOrderIfNeeded($alert)
    {
        // Check if auto-purchase is enabled for this product/location
        $inventoryItem = InventoryItem::where('product_id', $alert->product_id)
            ->where('variant_id', $alert->variant_id)
            ->where('location_id', $alert->location_id)
            ->first();

        if (!$inventoryItem) {
            return;
        }

        // Calculate suggested order quantity
        $suggestedQuantity = max(
            $inventoryItem->max_stock_level - $inventoryItem->available_quantity,
            $inventoryItem->reorder_point * 2
        );

        Log::info("Purchase order suggestion generated", [
            'alert_id' => $alert->id,
            'product_id' => $alert->product_id,
            'suggested_quantity' => $suggestedQuantity
        ]);

        // This would integrate with procurement system
        // For now, just log the suggestion
    }

    protected function checkAndDisableProductIfNeeded($alert)
    {
        // Check if product is out of stock across all locations
        $totalStock = InventoryItem::where('product_id', $alert->product_id)
            ->where('variant_id', $alert->variant_id)
            ->sum('available_quantity');

        if ($totalStock <= 0) {
            if ($alert->variant_id) {
                $alert->variant->update(['is_active' => false]);
            } else {
                $alert->product->update(['in_stock' => false]);
            }

            Log::info("Product/variant disabled due to zero stock", [
                'product_id' => $alert->product_id,
                'variant_id' => $alert->variant_id
            ]);
        }
    }

    protected function suggestOverstockActions($alert)
    {
        $suggestions = [
            'Create promotional campaign',
            'Transfer stock to other locations',
            'Consider discounted pricing',
            'Review forecasting parameters'
        ];

        Log::info("Overstock action suggestions", [
            'alert_id' => $alert->id,
            'suggestions' => $suggestions
        ]);
    }

    protected function generateAutomaticPurchaseOrder($alert)
    {
        // This would integrate with procurement/supplier management
        Log::info("Automatic purchase order triggered", [
            'alert_id' => $alert->id,
            'product_id' => $alert->product_id,
            'variant_id' => $alert->variant_id,
            'location_id' => $alert->location_id
        ]);
    }

    protected function getMaxStockLevel($alert)
    {
        $inventoryItem = InventoryItem::where('product_id', $alert->product_id)
            ->where('variant_id', $alert->variant_id)
            ->where('location_id', $alert->location_id)
            ->first();

        return $inventoryItem ? $inventoryItem->max_stock_level : 1000;
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Inventory alert processing job failed", [
            'alert_id' => $this->alertId,
            'error' => $exception->getMessage()
        ]);
    }
}