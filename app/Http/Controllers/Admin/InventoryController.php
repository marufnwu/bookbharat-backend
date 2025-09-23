<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    protected $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    public function overview()
    {
        $overview = [
            'total_products' => Product::count(),
            'total_stock_value' => $this->inventoryService->getStockValueReport()['total_value'] ?? 0,
            'in_stock_products' => Product::where('stock_quantity', '>', 0)->count(),
            'low_stock_products' => Product::where('stock_quantity', '>', 0)
                                          ->where('stock_quantity', '<=', 10)->count(),
            'out_of_stock_products' => Product::where('stock_quantity', '<=', 0)->count(),
            'stock_by_category' => $this->getStockByCategory(),
            'recent_movements' => $this->getRecentMovements(10),
            'stock_alerts' => $this->inventoryService->getLowStockProducts()
        ];

        return response()->json([
            'success' => true,
            'data' => $overview
        ]);
    }

    public function getLowStockProducts(Request $request)
    {
        $threshold = $request->threshold ?? 10;
        $products = $this->inventoryService->getLowStockProducts($threshold);

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    public function getOutOfStockProducts()
    {
        $products = $this->inventoryService->getOutOfStockProducts();

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    public function getMovements(Request $request)
    {
        $query = InventoryMovement::with(['product', 'location', 'user']);

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->location_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to);
        }

        $movements = $query->orderBy('created_at', 'desc')
                          ->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data' => $movements
        ]);
    }

    public function adjustStock(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'adjustment' => 'required|integer',
            'type' => 'required|in:add,subtract,set',
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string'
        ]);

        DB::beginTransaction();
        try {
            $product = Product::findOrFail($request->product_id);
            $oldStock = $product->stock_quantity;

            switch ($request->type) {
                case 'add':
                    $newStock = $oldStock + abs($request->adjustment);
                    break;
                case 'subtract':
                    $newStock = max(0, $oldStock - abs($request->adjustment));
                    break;
                case 'set':
                    $newStock = abs($request->adjustment);
                    break;
                default:
                    $newStock = $oldStock;
            }

            $product->update(['stock_quantity' => $newStock]);

            // Record movement
            InventoryMovement::create([
                'product_id' => $product->id,
                'type' => 'adjustment',
                'quantity' => $newStock - $oldStock,
                'old_quantity' => $oldStock,
                'new_quantity' => $newStock,
                'reason' => $request->reason,
                'notes' => $request->notes,
                'user_id' => auth()->id(),
                'location_id' => 1 // Default location
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stock adjusted successfully',
                'data' => [
                    'product_id' => $product->id,
                    'old_stock' => $oldStock,
                    'new_stock' => $newStock,
                    'adjustment' => $newStock - $oldStock
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to adjust stock: ' . $e->getMessage()
            ], 500);
        }
    }

    public function bulkUpdate(Request $request)
    {
        $request->validate([
            'updates' => 'required|array',
            'updates.*.product_id' => 'required|exists:products,id',
            'updates.*.stock_quantity' => 'required|integer|min:0',
            'reason' => 'required|string|max:255'
        ]);

        DB::beginTransaction();
        try {
            $updated = [];
            foreach ($request->updates as $update) {
                $product = Product::findOrFail($update['product_id']);
                $oldStock = $product->stock_quantity;
                $newStock = $update['stock_quantity'];

                if ($oldStock !== $newStock) {
                    $product->update(['stock_quantity' => $newStock]);

                    // Record movement
                    InventoryMovement::create([
                        'product_id' => $product->id,
                        'type' => 'bulk_update',
                        'quantity' => $newStock - $oldStock,
                        'old_quantity' => $oldStock,
                        'new_quantity' => $newStock,
                        'reason' => $request->reason,
                        'user_id' => auth()->id(),
                        'location_id' => 1
                    ]);

                    $updated[] = [
                        'product_id' => $product->id,
                        'old_stock' => $oldStock,
                        'new_stock' => $newStock
                    ];
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($updated) . ' products updated successfully',
                'data' => $updated
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update inventory: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getValueReport()
    {
        $report = $this->inventoryService->getStockValueReport();

        return response()->json([
            'success' => true,
            'data' => $report
        ]);
    }

    public function export(Request $request)
    {
        $query = Product::with('category');

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('stock_status')) {
            switch ($request->stock_status) {
                case 'in_stock':
                    $query->where('stock_quantity', '>', 0);
                    break;
                case 'low_stock':
                    $query->where('stock_quantity', '>', 0)
                          ->where('stock_quantity', '<=', 10);
                    break;
                case 'out_of_stock':
                    $query->where('stock_quantity', '<=', 0);
                    break;
            }
        }

        $products = $query->get();

        $csv = "Product ID,SKU,Name,Category,Stock Quantity,Price,Total Value\n";
        foreach ($products as $product) {
            $csv .= sprintf(
                "%d,%s,\"%s\",\"%s\",%d,%.2f,%.2f\n",
                $product->id,
                $product->sku,
                $product->name,
                $product->category->name ?? 'N/A',
                $product->stock_quantity,
                $product->price,
                $product->stock_quantity * $product->price
            );
        }

        return response($csv, 200)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="inventory_report_' . date('Y-m-d') . '.csv"');
    }

    private function getStockByCategory()
    {
        return DB::table('products')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->select(
                'categories.name as category',
                DB::raw('COUNT(products.id) as product_count'),
                DB::raw('SUM(products.stock_quantity) as total_stock'),
                DB::raw('SUM(products.stock_quantity * products.price) as stock_value')
            )
            ->groupBy('categories.id', 'categories.name')
            ->orderBy('stock_value', 'desc')
            ->limit(10)
            ->get();
    }

    private function getRecentMovements($limit = 10)
    {
        return InventoryMovement::with(['product:id,name', 'user:id,name'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($movement) {
                return [
                    'id' => $movement->id,
                    'product' => $movement->product->name ?? 'N/A',
                    'type' => $movement->type,
                    'quantity' => $movement->quantity,
                    'reason' => $movement->reason,
                    'user' => $movement->user->name ?? 'System',
                    'created_at' => $movement->created_at->format('Y-m-d H:i:s')
                ];
            });
    }
}