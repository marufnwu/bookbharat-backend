<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportController extends Controller
{
    /**
     * Sales Report
     */
    public function salesReport(Request $request)
    {
        $startDate = $request->input('start_date', now()->subDays(30));
        $endDate = $request->input('end_date', now());
        $groupBy = $request->input('group_by', 'day'); // day, week, month

        try {
            $orders = Order::whereBetween('created_at', [$startDate, $endDate])
                ->where('payment_status', 'paid')
                ->get();

            $totalRevenue = $orders->sum('total_amount');
            $totalOrders = $orders->count();
            $averageOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

            // Group by time period
            $salesByPeriod = $orders->groupBy(function ($order) use ($groupBy) {
                return match ($groupBy) {
                    'week' => $order->created_at->format('Y-W'),
                    'month' => $order->created_at->format('Y-m'),
                    default => $order->created_at->format('Y-m-d'),
                };
            })->map(function ($group) {
                return [
                    'total' => $group->sum('total_amount'),
                    'count' => $group->count(),
                ];
            });

            // Top selling products
            $topProducts = OrderItem::whereHas('order', function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('created_at', [$startDate, $endDate])
                        ->where('payment_status', 'paid');
                })
                ->select('product_id', DB::raw('SUM(quantity) as total_quantity'), DB::raw('SUM(total_price) as total_revenue'))
                ->with('product:id,name,sku')
                ->groupBy('product_id')
                ->orderBy('total_revenue', 'desc')
                ->limit(10)
                ->get();

            // Payment methods breakdown
            $paymentMethodsBreakdown = Payment::whereBetween('created_at', [$startDate, $endDate])
                ->where('status', 'completed')
                ->select('gateway', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total'))
                ->groupBy('gateway')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => [
                        'total_revenue' => $totalRevenue,
                        'total_orders' => $totalOrders,
                        'average_order_value' => round($averageOrderValue, 2),
                    ],
                    'sales_by_period' => $salesByPeriod,
                    'top_products' => $topProducts,
                    'payment_methods' => $paymentMethodsBreakdown,
                    'period' => [
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'group_by' => $groupBy,
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate sales report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Products Report
     */
    public function productsReport(Request $request)
    {
        try {
            $totalProducts = Product::count();
            $activeProducts = Product::where('is_active', true)->count();
            $outOfStock = Product::where('stock_quantity', 0)->count();
            $lowStock = Product::where('stock_quantity', '>', 0)->where('stock_quantity', '<=', 10)->count();

            // Products by category
            $byCategory = Product::select('category_id', DB::raw('COUNT(*) as count'))
                ->with('category:id,name')
                ->groupBy('category_id')
                ->get();

            // Best performing products (by revenue)
            $bestPerforming = OrderItem::select('product_id', DB::raw('SUM(total_price) as revenue'), DB::raw('SUM(quantity) as quantity_sold'))
                ->with('product:id,name,sku,price')
                ->groupBy('product_id')
                ->orderBy('revenue', 'desc')
                ->limit(20)
                ->get();

            // Worst performing products (low sales)
            $worstPerforming = Product::leftJoin('order_items', 'products.id', '=', 'order_items.product_id')
                ->select('products.id', 'products.name', 'products.sku', DB::raw('COALESCE(SUM(order_items.quantity), 0) as quantity_sold'))
                ->where('products.is_active', true)
                ->groupBy('products.id', 'products.name', 'products.sku')
                ->orderBy('quantity_sold', 'asc')
                ->limit(20)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => [
                        'total_products' => $totalProducts,
                        'active_products' => $activeProducts,
                        'out_of_stock' => $outOfStock,
                        'low_stock' => $lowStock,
                    ],
                    'by_category' => $byCategory,
                    'best_performing' => $bestPerforming,
                    'worst_performing' => $worstPerforming,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate products report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Customers Report
     */
    public function customersReport(Request $request)
    {
        try {
            $totalCustomers = User::role('customer')->count();
            $newCustomers = User::role('customer')->whereDate('created_at', '>=', now()->subDays(30))->count();

            // Top customers by revenue
            $topCustomers = User::role('customer')
                ->select('users.id', 'users.name', 'users.email', DB::raw('COUNT(orders.id) as total_orders'), DB::raw('SUM(orders.total_amount) as total_spent'))
                ->leftJoin('orders', 'users.id', '=', 'orders.user_id')
                ->where('orders.payment_status', 'paid')
                ->groupBy('users.id', 'users.name', 'users.email')
                ->orderBy('total_spent', 'desc')
                ->limit(20)
                ->get();

            // Customer acquisition by month
            $acquisitionByMonth = User::role('customer')
                ->select(DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'), DB::raw('COUNT(*) as count'))
                ->groupBy('month')
                ->orderBy('month', 'desc')
                ->limit(12)
                ->get();

            // Customer lifetime value distribution
            $lifetimeValueRanges = [
                '0-500' => User::role('customer')->whereHas('orders', function ($q) {
                    $q->havingRaw('SUM(total_amount) <= 500');
                })->count(),
                '501-1000' => User::role('customer')->whereHas('orders', function ($q) {
                    $q->havingRaw('SUM(total_amount) > 500 AND SUM(total_amount) <= 1000');
                })->count(),
                '1001-5000' => User::role('customer')->whereHas('orders', function ($q) {
                    $q->havingRaw('SUM(total_amount) > 1000 AND SUM(total_amount) <= 5000');
                })->count(),
                '5000+' => User::role('customer')->whereHas('orders', function ($q) {
                    $q->havingRaw('SUM(total_amount) > 5000');
                })->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => [
                        'total_customers' => $totalCustomers,
                        'new_customers_30_days' => $newCustomers,
                    ],
                    'top_customers' => $topCustomers,
                    'acquisition_by_month' => $acquisitionByMonth,
                    'lifetime_value_distribution' => $lifetimeValueRanges,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate customers report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Inventory Report
     */
    public function inventoryReport(Request $request)
    {
        try {
            $totalValue = Product::where('is_active', true)
                ->get()
                ->sum(function ($product) {
                    return $product->stock_quantity * $product->price;
                });

            $totalProducts = Product::count();
            $inStock = Product::where('stock_quantity', '>', 0)->count();
            $outOfStock = Product::where('stock_quantity', 0)->count();
            $lowStock = Product::where('stock_quantity', '>', 0)->where('stock_quantity', '<=', 10)->count();

            // Products requiring restock
            $requireRestock = Product::where('stock_quantity', '<=', 10)
                ->select('id', 'name', 'sku', 'stock_quantity', 'price')
                ->orderBy('stock_quantity', 'asc')
                ->limit(50)
                ->get();

            // Inventory value by category
            $valueByCategory = Product::select('category_id', DB::raw('SUM(stock_quantity * price) as inventory_value'), DB::raw('SUM(stock_quantity) as total_quantity'))
                ->with('category:id,name')
                ->where('is_active', true)
                ->groupBy('category_id')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => [
                        'total_inventory_value' => round($totalValue, 2),
                        'total_products' => $totalProducts,
                        'in_stock' => $inStock,
                        'out_of_stock' => $outOfStock,
                        'low_stock' => $lowStock,
                    ],
                    'require_restock' => $requireRestock,
                    'value_by_category' => $valueByCategory,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate inventory report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Taxes Report
     */
    public function taxesReport(Request $request)
    {
        $startDate = $request->input('start_date', now()->subDays(30));
        $endDate = $request->input('end_date', now());

        try {
            $orders = Order::whereBetween('created_at', [$startDate, $endDate])
                ->where('payment_status', 'paid')
                ->get();

            $totalTaxCollected = $orders->sum('tax_amount');
            $totalOrders = $orders->count();

            // Tax by month
            $taxByMonth = $orders->groupBy(function ($order) {
                return $order->created_at->format('Y-m');
            })->map(function ($group) {
                return [
                    'tax_amount' => $group->sum('tax_amount'),
                    'orders_count' => $group->count(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => [
                        'total_tax_collected' => round($totalTaxCollected, 2),
                        'total_orders' => $totalOrders,
                    ],
                    'tax_by_month' => $taxByMonth,
                    'period' => [
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate taxes report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Coupons Report
     */
    public function couponsReport(Request $request)
    {
        try {
            $totalCoupons = Coupon::count();
            $activeCoupons = Coupon::where('is_active', true)
                ->where('valid_from', '<=', now())
                ->where('valid_to', '>=', now())
                ->count();

            // Coupon usage stats
            $couponUsage = Coupon::withCount('usages')
                ->with('usages:coupon_id,discount_amount')
                ->get()
                ->map(function ($coupon) {
                    return [
                        'id' => $coupon->id,
                        'code' => $coupon->code,
                        'type' => $coupon->discount_type,
                        'value' => $coupon->discount_value,
                        'usage_count' => $coupon->usages_count,
                        'total_discount' => $coupon->usages->sum('discount_amount'),
                        'usage_limit' => $coupon->max_uses,
                        'is_active' => $coupon->is_active,
                    ];
                });

            // Most used coupons
            $mostUsed = $couponUsage->sortByDesc('usage_count')->take(10)->values();

            // Total discount given
            $totalDiscount = $couponUsage->sum('total_discount');

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => [
                        'total_coupons' => $totalCoupons,
                        'active_coupons' => $activeCoupons,
                        'total_discount_given' => round($totalDiscount, 2),
                    ],
                    'coupon_usage' => $couponUsage,
                    'most_used' => $mostUsed,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate coupons report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Shipping Report
     */
    public function shippingReport(Request $request)
    {
        $startDate = $request->input('start_date', now()->subDays(30));
        $endDate = $request->input('end_date', now());

        try {
            $orders = Order::whereBetween('created_at', [$startDate, $endDate])
                ->where('payment_status', 'paid')
                ->get();

            $totalShippingRevenue = $orders->sum('shipping_amount');
            $averageShippingCost = $orders->count() > 0 ? $totalShippingRevenue / $orders->count() : 0;

            // Orders by shipping method
            $byShippingMethod = $orders->groupBy('shipping_method')->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'revenue' => $group->sum('shipping_amount'),
                ];
            });

            // Orders by state/zone
            $byState = $orders->groupBy('shipping_address.state')->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'shipping_revenue' => $group->sum('shipping_amount'),
                ];
            })->take(10);

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => [
                        'total_shipping_revenue' => round($totalShippingRevenue, 2),
                        'average_shipping_cost' => round($averageShippingCost, 2),
                        'total_orders' => $orders->count(),
                    ],
                    'by_shipping_method' => $byShippingMethod,
                    'by_state' => $byState,
                    'period' => [
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate shipping report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate Custom Report
     */
    public function generateCustomReport(Request $request)
    {
        $request->validate([
            'report_type' => 'required|in:sales,products,customers,inventory,taxes,coupons,shipping',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'format' => 'sometimes|in:json,csv,pdf',
        ]);

        try {
            $reportType = $request->report_type;
            $request->merge([
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
            ]);

            $response = match ($reportType) {
                'sales' => $this->salesReport($request),
                'products' => $this->productsReport($request),
                'customers' => $this->customersReport($request),
                'inventory' => $this->inventoryReport($request),
                'taxes' => $this->taxesReport($request),
                'coupons' => $this->couponsReport($request),
                'shipping' => $this->shippingReport($request),
                default => response()->json(['success' => false, 'message' => 'Invalid report type'], 400),
            };

            return $response;
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate custom report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Scheduled Reports
     */
    public function getScheduledReports(Request $request)
    {
        // Placeholder - would need a scheduled_reports table
        $scheduledReports = [
            [
                'id' => 1,
                'name' => 'Monthly Sales Report',
                'type' => 'sales',
                'frequency' => 'monthly',
                'recipients' => ['admin@bookbharat.com'],
                'last_run' => now()->subMonth()->toDateTimeString(),
                'next_run' => now()->startOfMonth()->toDateTimeString(),
                'is_active' => true,
            ],
        ];

        return response()->json([
            'success' => true,
            'reports' => $scheduledReports,
            'message' => 'Scheduled reports functionality coming soon'
        ]);
    }

    /**
     * Schedule Report
     */
    public function scheduleReport(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'report_type' => 'required|in:sales,products,customers,inventory,taxes,coupons,shipping',
            'frequency' => 'required|in:daily,weekly,monthly',
            'recipients' => 'required|array',
            'recipients.*' => 'email',
        ]);

        // Placeholder - would need actual scheduler implementation
        return response()->json([
            'success' => true,
            'message' => 'Report scheduling functionality coming soon',
            'data' => $request->all()
        ]);
    }
}
