<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\StockAlert;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InventoryController extends Controller
{
    /**
     * Get real-time stock levels for products
     */
    public function getStock(Request $request)
    {
        $request->validate([
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id',
            'variant_ids' => 'nullable|array',
            'variant_ids.*' => 'exists:product_variants,id',
            'location_id' => 'nullable|exists:inventory_locations,id',
        ]);

        $locationId = $request->get('location_id');
        $variantIds = $request->get('variant_ids', []);

        $query = InventoryItem::with(['product', 'variant', 'location'])
            ->whereIn('product_id', $request->product_ids);

        if ($locationId) {
            $query->where('location_id', $locationId);
        }

        if (!empty($variantIds)) {
            $query->whereIn('variant_id', $variantIds);
        }

        $inventory = $query->get();

        $stockData = $inventory->groupBy('product_id')->map(function ($items, $productId) {
            $product = $items->first()->product;
            $totalAvailable = $items->sum('available_quantity');
            $totalReserved = $items->sum('reserved_quantity');
            $locations = $items->map(function ($item) {
                return [
                    'location_id' => $item->location_id,
                    'location_name' => $item->location->name,
                    'available' => $item->available_quantity,
                    'reserved' => $item->reserved_quantity,
                    'actual_available' => $item->actual_available,
                    'stock_status' => $item->stock_status,
                ];
            });

            $variants = [];
            if ($items->first()->variant_id) {
                $variants = $items->groupBy('variant_id')->map(function ($variantItems, $variantId) {
                    return [
                        'variant_id' => $variantId,
                        'total_available' => $variantItems->sum('available_quantity'),
                        'total_reserved' => $variantItems->sum('reserved_quantity'),
                        'locations' => $variantItems->map(function ($item) {
                            return [
                                'location_id' => $item->location_id,
                                'available' => $item->available_quantity,
                                'reserved' => $item->reserved_quantity,
                            ];
                        }),
                    ];
                });
            }

            return [
                'product_id' => $productId,
                'product_name' => $product->name,
                'total_available' => $totalAvailable,
                'total_reserved' => $totalReserved,
                'actual_available' => $totalAvailable - $totalReserved,
                'is_in_stock' => $totalAvailable > $totalReserved,
                'stock_status' => $this->getOverallStockStatus($items),
                'locations' => $locations,
                'variants' => $variants,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $stockData->values(),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Check stock availability for cart items
     */
    public function checkAvailability(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.variant_id' => 'nullable|exists:product_variants,id',
            'items.*.quantity' => 'required|integer|min:1',
            'location_id' => 'nullable|exists:inventory_locations,id',
        ]);

        $results = [];
        $allAvailable = true;

        foreach ($request->items as $item) {
            $availability = $this->checkItemAvailability(
                $item['product_id'],
                $item['variant_id'] ?? null,
                $item['quantity'],
                $request->get('location_id')
            );

            $results[] = $availability;
            
            if (!$availability['is_available']) {
                $allAvailable = false;
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'all_available' => $allAvailable,
                'items' => $results,
                'alternatives' => $this->suggestAlternatives($results),
            ]
        ]);
    }

    /**
     * Get stock alerts for admin users
     */
    public function getStockAlerts(Request $request)
    {
        // Check if user has admin permissions
        if (!Auth::user() || !Auth::user()->can('view_inventory')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $query = StockAlert::with(['product', 'variant', 'location'])
            ->active()
            ->orderBy('triggered_at', 'desc');

        if ($request->alert_type) {
            $query->where('alert_type', $request->alert_type);
        }

        if ($request->location_id) {
            $query->where('location_id', $request->location_id);
        }

        $alerts = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $alerts->items(),
            'pagination' => [
                'current_page' => $alerts->currentPage(),
                'per_page' => $alerts->perPage(),
                'total' => $alerts->total(),
                'last_page' => $alerts->lastPage(),
            ],
            'summary' => [
                'total_alerts' => $alerts->total(),
                'critical_alerts' => StockAlert::active()->critical()->count(),
                'by_type' => StockAlert::active()
                    ->selectRaw('alert_type, COUNT(*) as count')
                    ->groupBy('alert_type')
                    ->pluck('count', 'alert_type'),
            ]
        ]);
    }

    /**
     * Get inventory movements/history
     */
    public function getMovements(Request $request, $productId)
    {
        $request->validate([
            'days' => 'integer|max:365',
            'location_id' => 'nullable|exists:inventory_locations,id',
            'variant_id' => 'nullable|exists:product_variants,id',
        ]);

        $product = Product::findOrFail($productId);
        $days = $request->get('days', 30);

        $query = $product->inventoryMovements()
            ->with(['location', 'variant', 'createdBy'])
            ->where('created_at', '>=', now()->subDays($days));

        if ($request->location_id) {
            $query->where('location_id', $request->location_id);
        }

        if ($request->variant_id) {
            $query->where('variant_id', $request->variant_id);
        }

        $movements = $query->orderBy('created_at', 'desc')->paginate(50);

        $summary = [
            'total_movements' => $movements->total(),
            'total_in' => $query->where('type', 'in')->sum('quantity'),
            'total_out' => $query->where('type', 'out')->sum('quantity'),
            'net_change' => $query->selectRaw('SUM(CASE WHEN type = "in" THEN quantity ELSE -quantity END)')->value('net_change') ?? 0,
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                ],
                'movements' => $movements->items(),
                'summary' => $summary,
                'pagination' => [
                    'current_page' => $movements->currentPage(),
                    'per_page' => $movements->perPage(),
                    'total' => $movements->total(),
                    'last_page' => $movements->lastPage(),
                ]
            ]
        ]);
    }

    /**
     * Reserve stock for order processing
     */
    public function reserveStock(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.variant_id' => 'nullable|exists:product_variants,id',
            'items.*.quantity' => 'required|integer|min:1',
            'order_id' => 'required|exists:orders,id',
        ]);

        $results = [];
        $allReserved = true;

        foreach ($request->items as $item) {
            $reserved = $this->reserveItemStock(
                $item['product_id'],
                $item['variant_id'] ?? null,
                $item['quantity'],
                $request->order_id
            );

            $results[] = $reserved;

            if (!$reserved['success']) {
                $allReserved = false;
            }
        }

        return response()->json([
            'success' => $allReserved,
            'data' => [
                'all_reserved' => $allReserved,
                'items' => $results,
                'order_id' => $request->order_id,
            ]
        ]);
    }

    /**
     * Helper method to check individual item availability
     */
    protected function checkItemAvailability($productId, $variantId, $quantity, $locationId = null)
    {
        $query = InventoryItem::where('product_id', $productId);

        if ($variantId) {
            $query->where('variant_id', $variantId);
        }

        if ($locationId) {
            $query->where('location_id', $locationId);
        }

        $inventory = $query->get();
        $totalAvailable = $inventory->sum('actual_available');

        return [
            'product_id' => $productId,
            'variant_id' => $variantId,
            'requested_quantity' => $quantity,
            'available_quantity' => $totalAvailable,
            'is_available' => $totalAvailable >= $quantity,
            'shortage' => max(0, $quantity - $totalAvailable),
        ];
    }

    /**
     * Helper method to reserve stock for individual item
     */
    protected function reserveItemStock($productId, $variantId, $quantity, $orderId)
    {
        // Find inventory items with available stock
        $query = InventoryItem::where('product_id', $productId)
            ->whereRaw('available_quantity > reserved_quantity');

        if ($variantId) {
            $query->where('variant_id', $variantId);
        }

        $inventoryItems = $query->orderBy('available_quantity', 'desc')->get();
        
        $remainingToReserve = $quantity;
        $reservedItems = [];

        foreach ($inventoryItems as $item) {
            if ($remainingToReserve <= 0) break;

            $availableToReserve = $item->actual_available;
            $reserveQty = min($remainingToReserve, $availableToReserve);

            if ($reserveQty > 0) {
                $item->increment('reserved_quantity', $reserveQty);
                $remainingToReserve -= $reserveQty;
                
                $reservedItems[] = [
                    'location_id' => $item->location_id,
                    'quantity' => $reserveQty,
                ];
            }
        }

        return [
            'product_id' => $productId,
            'variant_id' => $variantId,
            'requested_quantity' => $quantity,
            'reserved_quantity' => $quantity - $remainingToReserve,
            'success' => $remainingToReserve === 0,
            'reserved_locations' => $reservedItems,
        ];
    }

    /**
     * Helper method to determine overall stock status
     */
    protected function getOverallStockStatus($items)
    {
        $statuses = $items->pluck('stock_status')->unique();
        
        if ($statuses->contains('out_of_stock')) {
            return 'out_of_stock';
        } elseif ($statuses->contains('low_stock')) {
            return 'low_stock';
        } else {
            return 'in_stock';
        }
    }

    /**
     * Helper method to suggest alternatives when items are unavailable
     */
    protected function suggestAlternatives($results)
    {
        $alternatives = [];
        
        foreach ($results as $result) {
            if (!$result['is_available']) {
                // Find similar products in the same category
                $product = Product::find($result['product_id']);
                $similar = Product::active()
                    ->inStock()
                    ->where('category_id', $product->category_id)
                    ->where('id', '!=', $product->id)
                    ->limit(3)
                    ->get(['id', 'name', 'price']);

                $alternatives[$result['product_id']] = $similar;
            }
        }
        
        return $alternatives;
    }
}