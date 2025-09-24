<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\InventoryMovement;
use App\Models\StockAlert;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventoryService
{
    public function updateStock(Product $product, int $quantity, string $type = 'adjustment', string $reason = null)
    {
        DB::beginTransaction();
        
        try {
            $oldQuantity = $product->stock_quantity;
            
            match($type) {
                'increase' => $product->increment('stock_quantity', $quantity),
                'decrease' => $product->decrement('stock_quantity', $quantity),
                'set' => $product->update(['stock_quantity' => $quantity]),
                default => $product->update(['stock_quantity' => $quantity])
            };
            
            // Create inventory movement record
            InventoryMovement::create([
                'product_id' => $product->id,
                'variant_id' => null,
                'movement_type' => $type,
                'quantity_change' => $type === 'set' ? ($quantity - $oldQuantity) : 
                                   ($type === 'increase' ? $quantity : -$quantity),
                'quantity_before' => $oldQuantity,
                'quantity_after' => $product->fresh()->stock_quantity,
                'reason' => $reason ?? 'Manual adjustment',
                'created_by' => auth()->id(),
            ]);
            
            // Check for low stock alerts
            $this->checkLowStockAlert($product);
            
            DB::commit();
            
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Inventory update failed', [
                'product_id' => $product->id,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    public function updateVariantStock(ProductVariant $variant, int $quantity, string $type = 'adjustment', string $reason = null)
    {
        DB::beginTransaction();
        
        try {
            $oldQuantity = $variant->stock_quantity ?? 0;
            
            match($type) {
                'increase' => $variant->increment('stock_quantity', $quantity),
                'decrease' => $variant->decrement('stock_quantity', $quantity),
                'set' => $variant->update(['stock_quantity' => $quantity]),
                default => $variant->update(['stock_quantity' => $quantity])
            };
            
            // Create inventory movement record
            InventoryMovement::create([
                'product_id' => $variant->product_id,
                'variant_id' => $variant->id,
                'movement_type' => $type,
                'quantity_change' => $type === 'set' ? ($quantity - $oldQuantity) : 
                                   ($type === 'increase' ? $quantity : -$quantity),
                'quantity_before' => $oldQuantity,
                'quantity_after' => $variant->fresh()->stock_quantity,
                'reason' => $reason ?? 'Manual adjustment',
                'created_by' => auth()->id(),
            ]);
            
            // Update parent product stock
            $this->updateProductStockFromVariants($variant->product);
            
            DB::commit();
            
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Variant inventory update failed', [
                'variant_id' => $variant->id,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    public function reserveStock(Product $product, int $quantity, string $reference = null)
    {
        if ($product->stock_quantity < $quantity) {
            return false;
        }
        
        return $this->updateStock($product, $quantity, 'decrease', "Reserved for {$reference}");
    }
    
    public function releaseReservedStock(Product $product, int $quantity, string $reference = null)
    {
        return $this->updateStock($product, $quantity, 'increase', "Released from {$reference}");
    }
    
    public function getStockMovementHistory(Product $product, int $days = 30)
    {
        return InventoryMovement::where('product_id', $product->id)
            ->with('createdBy:id,name')
            ->where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at', 'desc')
            ->get();
    }
    
    public function getLowStockProducts(int $threshold = null)
    {
        $threshold = $threshold ?? 10;
        
        return Product::where('manage_stock', true)
            ->where('stock_quantity', '>', 0)
            ->where('stock_quantity', '<=', $threshold)
            ->with('category:id,name')
            ->orderBy('stock_quantity', 'asc')
            ->get();
    }
    
    public function getOutOfStockProducts()
    {
        return Product::where('manage_stock', true)
            ->where('stock_quantity', '<=', 0)
            ->with('category:id,name')
            ->orderBy('updated_at', 'desc')
            ->get();
    }
    
    public function getStockValueReport()
    {
        $totalProducts = Product::where('manage_stock', true)->count();
        $inStockProducts = Product::where('manage_stock', true)->where('stock_quantity', '>', 0)->count();
        $outOfStockProducts = Product::where('manage_stock', true)->where('stock_quantity', '<=', 0)->count();
        
        $totalStockValue = Product::where('manage_stock', true)
            ->selectRaw('SUM(stock_quantity * cost_price) as total_value')
            ->value('total_value') ?? 0;
        
        $totalStockQuantity = Product::where('manage_stock', true)->sum('stock_quantity');
        
        return [
            'total_products' => $totalProducts,
            'in_stock_products' => $inStockProducts,
            'out_of_stock_products' => $outOfStockProducts,
            'low_stock_products' => $this->getLowStockProducts()->count(),
            'total_stock_value' => $totalStockValue,
            'total_stock_quantity' => $totalStockQuantity,
            'stock_percentage' => $totalProducts > 0 ? ($inStockProducts / $totalProducts) * 100 : 0,
        ];
    }
    
    protected function updateProductStockFromVariants(Product $product)
    {
        $totalVariantStock = $product->variants()->sum('stock_quantity') ?? 0;
        $product->update(['stock_quantity' => $totalVariantStock]);
    }
    
    protected function checkLowStockAlert(Product $product)
    {
        $threshold = $product->min_stock_level ?? 10;
        
        if ($product->stock_quantity <= $threshold && $product->stock_quantity > 0) {
            // Create or update stock alert
            StockAlert::updateOrCreate([
                'product_id' => $product->id,
                'variant_id' => null,
                'type' => 'low_stock',
                'is_resolved' => false,
            ], [
                'threshold' => $threshold,
                'current_quantity' => $product->stock_quantity,
                'message' => "Product '{$product->name}' is running low on stock",
                'severity' => 'medium',
            ]);
        } elseif ($product->stock_quantity <= 0) {
            // Create out of stock alert
            StockAlert::updateOrCreate([
                'product_id' => $product->id,
                'variant_id' => null,
                'type' => 'out_of_stock',
                'is_resolved' => false,
            ], [
                'threshold' => 0,
                'current_quantity' => 0,
                'message' => "Product '{$product->name}' is out of stock",
                'severity' => 'high',
            ]);
        } else {
            // Resolve existing alerts if stock is sufficient
            StockAlert::where('product_id', $product->id)
                ->where('variant_id', null)
                ->where('is_resolved', false)
                ->update(['is_resolved' => true]);
        }
    }
    
    public function adjustInventory($productId, array $adjustmentData)
    {
        $product = Product::find($productId);

        if (!$product) {
            throw new \Exception("Product not found with ID: {$productId}");
        }

        $adjustment = $adjustmentData['adjustment'] ?? 0;
        $adjustmentType = $adjustmentData['adjustment_type'] ?? 'adjustment';
        $notes = $adjustmentData['notes'] ?? 'Inventory adjustment';

        if ($adjustment == 0) {
            return true; // No adjustment needed
        }

        $type = $adjustment > 0 ? 'increase' : 'decrease';
        $quantity = abs($adjustment);

        return $this->updateStock($product, $quantity, $type, $notes);
    }

    public function bulkUpdateStock(array $updates)
    {
        DB::beginTransaction();

        try {
            foreach ($updates as $update) {
                $product = Product::find($update['product_id']);
                if ($product) {
                    $this->updateStock(
                        $product,
                        $update['quantity'],
                        $update['type'] ?? 'set',
                        $update['reason'] ?? 'Bulk update'
                    );
                }
            }

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk inventory update failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
}