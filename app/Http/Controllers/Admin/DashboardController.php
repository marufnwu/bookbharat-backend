<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Product;
use App\Models\Order;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\PromotionalCampaign;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    protected $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
        // Simplified for now - admin role check is handled at route level
    }

    public function overview()
    {
        $cacheKey = 'admin_dashboard_overview_' . now()->format('Y-m-d-H');
        
        $data = Cache::remember($cacheKey, 3600, function () {
            return [
                'stats' => $this->getOverviewStats(),
                'sales_chart' => $this->getSalesChartData(),
                'recent_orders' => $this->getRecentOrders(),
                'top_products' => $this->getTopProducts(),
                'low_stock_alerts' => $this->getLowStockAlerts(),
                'customer_insights' => $this->getCustomerInsights(),
                'revenue_metrics' => $this->getRevenueMetrics(),
                'campaign_performance' => $this->getCampaignPerformance(),
            ];
        });

        return response()->json([
            'success' => true,
            'dashboard' => $data
        ]);
    }

    public function salesAnalytics(Request $request)
    {
        $period = $request->input('period', '30d'); // 7d, 30d, 90d, 1y
        $comparison = $request->input('comparison', true);

        $analytics = [
            'current_period' => $this->getSalesPeriodData($period),
            'chart_data' => $this->getSalesChartData($period),
            'product_performance' => $this->getProductPerformance($period),
            'category_performance' => $this->getCategoryPerformance($period),
            'geographic_sales' => $this->getGeographicSales($period),
            'customer_segments' => $this->getCustomerSegmentSales($period),
        ];

        if ($comparison) {
            $analytics['comparison_period'] = $this->getSalesPeriodData($period, true);
            $analytics['growth_metrics'] = $this->calculateGrowthMetrics($analytics['current_period'], $analytics['comparison_period']);
        }

        return response()->json([
            'success' => true,
            'period' => $period,
            'analytics' => $analytics
        ]);
    }

    public function customerAnalytics()
    {
        $analytics = [
            'customer_metrics' => $this->getCustomerMetrics(),
            'acquisition_channels' => $this->getAcquisitionChannels(),
            'customer_lifetime_value' => $this->getCustomerLifetimeValue(),
            'retention_analysis' => $this->getRetentionAnalysis(),
            'customer_segments' => $this->getDetailedCustomerSegments(),
            'churn_analysis' => $this->getChurnAnalysis(),
        ];

        return response()->json([
            'success' => true,
            'customer_analytics' => $analytics
        ]);
    }

    public function inventoryOverview()
    {
        $inventory = [
            'stock_summary' => $this->inventoryService->getStockValueReport(),
            'low_stock_products' => $this->inventoryService->getLowStockProducts(),
            'out_of_stock_products' => $this->inventoryService->getOutOfStockProducts(),
        ];

        return response()->json([
            'success' => true,
            'inventory' => $inventory
        ]);
    }

    public function orderInsights(Request $request)
    {
        $period = $request->input('period', '30d');

        $insights = [
            'order_statistics' => $this->getOrderStatistics($period),
            'order_status_breakdown' => $this->getOrderStatusBreakdown($period),
            'fulfillment_metrics' => $this->getFulfillmentMetrics($period),
            'payment_method_analysis' => $this->getPaymentMethodAnalysis($period),
            'return_analysis' => $this->getReturnAnalysis($period),
            'shipping_performance' => $this->getShippingPerformance($period),
        ];

        return response()->json([
            'success' => true,
            'order_insights' => $insights
        ]);
    }

    public function marketingPerformance()
    {
        $performance = [
            'campaign_overview' => $this->getCampaignOverview(),
            'coupon_performance' => $this->getCouponPerformance(),
            'email_marketing_stats' => $this->getEmailMarketingStats(),
            'social_commerce_metrics' => $this->getSocialCommerceMetrics(),
            'referral_program_stats' => $this->getReferralProgramStats(),
            'customer_acquisition_cost' => $this->getCustomerAcquisitionCost(),
        ];

        return response()->json([
            'success' => true,
            'marketing_performance' => $performance
        ]);
    }

    protected function getOverviewStats(): array
    {
        $today = now();
        $yesterday = now()->subDay();
        $thisMonth = now()->startOfMonth();
        $lastMonth = now()->subMonth()->startOfMonth();

        return [
            'total_revenue' => [
                'value' => Order::where('status', 'delivered')->sum('total_amount'),
                'today' => Order::where('status', 'delivered')->whereDate('created_at', $today)->sum('total_amount'),
                'change' => $this->calculatePercentageChange(
                    Order::where('status', 'delivered')->whereDate('created_at', $today)->sum('total_amount'),
                    Order::where('status', 'delivered')->whereDate('created_at', $yesterday)->sum('total_amount')
                )
            ],
            'total_orders' => [
                'value' => Order::count(),
                'today' => Order::whereDate('created_at', $today)->count(),
                'pending' => Order::where('status', 'pending')->count(),
                'processing' => Order::where('status', 'processing')->count(),
            ],
            'total_customers' => [
                'value' => User::count(),
                'new_today' => User::whereDate('created_at', $today)->count(),
                'active_this_month' => User::whereHas('orders', function ($query) use ($thisMonth) {
                    $query->where('created_at', '>=', $thisMonth);
                })->count(),
            ],
            'total_products' => [
                'value' => Product::count(),
                'in_stock' => Product::where('stock_quantity', '>', 0)->count(),
                'low_stock' => Product::where('stock_quantity', '>', 0)
                                    ->where('stock_quantity', '<=', 10)->count(),
                'out_of_stock' => Product::where('stock_quantity', '<=', 0)->count(),
            ],
            'conversion_rate' => $this->calculateConversionRate(),
            'average_order_value' => Order::where('status', 'delivered')->avg('total_amount'),
        ];
    }

    protected function getSalesChartData(string $period = '30d'): array
    {
        $days = (int) filter_var($period, FILTER_SANITIZE_NUMBER_INT);
        $startDate = now()->subDays($days);

        $salesData = Order::where('status', 'delivered')
            ->where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, SUM(total_amount) as revenue, COUNT(*) as orders')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'labels' => $salesData->pluck('date')->toArray(),
            'revenue' => $salesData->pluck('revenue')->toArray(),
            'orders' => $salesData->pluck('orders')->toArray(),
        ];
    }

    protected function getRecentOrders(): array
    {
        return Order::with(['user:id,name,email', 'orderItems.product:id,name'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer' => $order->user->name,
                    'total_amount' => $order->total_amount,
                    'status' => $order->status,
                    'items_count' => $order->orderItems->count(),
                    'created_at' => $order->created_at->format('M d, Y H:i'),
                ];
            })
            ->toArray();
    }

    protected function getTopProducts(): array
    {
        return Product::withCount(['orderItems' => function ($query) {
            $query->whereHas('order', function ($q) {
                $q->where('status', 'delivered')
                  ->where('created_at', '>=', now()->subDays(30));
            });
        }])
        ->orderBy('order_items_count', 'desc')
        ->limit(10)
        ->get(['id', 'name', 'price', 'primary_image'])
        ->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'image' => $product->primary_image_url,
                'sales_count' => $product->order_items_count,
            ];
        })
        ->toArray();
    }

    protected function getLowStockAlerts(): array
    {
        return Product::where('stock_quantity', '>', 0)
            ->where('stock_quantity', '<=', 10)
            ->orderBy('stock_quantity')
            ->limit(10)
            ->get(['id', 'name', 'stock_quantity', 'sku'])
            ->toArray();
    }

    protected function getCustomerInsights(): array
    {
        return [
            'new_customers_this_month' => User::where('created_at', '>=', now()->startOfMonth())->count(),
            'repeat_customers' => DB::table('users')
                ->join('orders', 'users.id', '=', 'orders.user_id')
                ->groupBy('users.id')
                ->havingRaw('COUNT(orders.id) > 1')
                ->count(),
            'top_spending_customers' => User::withSum(['orders as total_spent' => function ($query) {
                $query->where('status', 'delivered');
            }], 'total_amount')
            ->orderBy('total_spent', 'desc')
            ->limit(5)
            ->get(['id', 'name', 'email'])
            ->map(function ($user) {
                return [
                    'name' => $user->name,
                    'email' => $user->email,
                    'total_spent' => $user->total_spent ?? 0,
                ];
            }),
        ];
    }

    protected function getRevenueMetrics(): array
    {
        $thisMonth = Order::where('status', 'delivered')
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('total_amount');

        $lastMonth = Order::where('status', 'delivered')
            ->whereBetween('created_at', [
                now()->subMonth()->startOfMonth(),
                now()->subMonth()->endOfMonth()
            ])
            ->sum('total_amount');

        return [
            'this_month' => $thisMonth,
            'last_month' => $lastMonth,
            'growth' => $this->calculatePercentageChange($thisMonth, $lastMonth),
            'monthly_target' => 500000, // This could be configurable
            'target_progress' => $thisMonth > 0 ? ($thisMonth / 500000) * 100 : 0,
        ];
    }

    protected function getCampaignPerformance(): array
    {
        // Simplified for initial testing
        try {
            $activeCampaigns = PromotionalCampaign::where('is_active', true)->count();
            $totalCouponsUsed = DB::table('coupon_usages')
                ->where('created_at', '>=', now()->subDays(30))
                ->count();

            return [
                'active_campaigns' => $activeCampaigns,
                'coupons_used_this_month' => $totalCouponsUsed,
                'top_performing_coupons' => [],
            ];
        } catch (\Exception $e) {
            return [
                'active_campaigns' => 0,
                'coupons_used_this_month' => 0,
                'top_performing_coupons' => [],
            ];
        }
    }

    protected function calculatePercentageChange($current, $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }

        return (($current - $previous) / $previous) * 100;
    }

    protected function calculateConversionRate(): float
    {
        $totalUsers = User::count();
        $customersWithOrders = User::whereHas('orders')->count();

        if ($totalUsers == 0) {
            return 0;
        }

        return ($customersWithOrders / $totalUsers) * 100;
    }

    protected function getSalesPeriodData(string $period, bool $isPrevious = false): array
    {
        $days = (int) filter_var($period, FILTER_SANITIZE_NUMBER_INT);
        
        if ($isPrevious) {
            $endDate = now()->subDays($days);
            $startDate = $endDate->copy()->subDays($days);
        } else {
            $startDate = now()->subDays($days);
            $endDate = now();
        }

        $orders = Order::where('status', 'delivered')
            ->whereBetween('created_at', [$startDate, $endDate]);

        return [
            'total_revenue' => $orders->sum('total_amount'),
            'total_orders' => $orders->count(),
            'average_order_value' => $orders->avg('total_amount'),
            'unique_customers' => $orders->distinct('user_id')->count('user_id'),
        ];
    }

    protected function getProductPerformance(string $period): array
    {
        $days = (int) filter_var($period, FILTER_SANITIZE_NUMBER_INT);
        
        return Product::withCount(['orderItems as sales_count' => function ($query) use ($days) {
            $query->whereHas('order', function ($q) use ($days) {
                $q->where('status', 'delivered')
                  ->where('created_at', '>=', now()->subDays($days));
            });
        }])
        ->withSum(['orderItems as revenue' => function ($query) use ($days) {
            $query->whereHas('order', function ($q) use ($days) {
                $q->where('status', 'delivered')
                  ->where('created_at', '>=', now()->subDays($days));
            });
        }], 'total_price')
        ->orderBy('revenue', 'desc')
        ->limit(20)
        ->get(['id', 'name', 'price'])
        ->toArray();
    }

    protected function getCategoryPerformance(string $period): array
    {
        $days = (int) filter_var($period, FILTER_SANITIZE_NUMBER_INT);
        
        return Category::withCount(['products.orderItems as sales_count' => function ($query) use ($days) {
            $query->whereHas('order', function ($q) use ($days) {
                $q->where('status', 'delivered')
                  ->where('created_at', '>=', now()->subDays($days));
            });
        }])
        ->orderBy('sales_count', 'desc')
        ->get(['id', 'name'])
        ->toArray();
    }

    protected function getGeographicSales(string $period): array
    {
        $days = (int) filter_var($period, FILTER_SANITIZE_NUMBER_INT);
        
        return Order::where('status', 'delivered')
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('JSON_EXTRACT(shipping_address, "$.city") as city, COUNT(*) as orders, SUM(total_amount) as revenue')
            ->groupBy('city')
            ->orderBy('revenue', 'desc')
            ->limit(10)
            ->get()
            ->toArray();
    }

    protected function getCustomerSegmentSales(string $period): array
    {
        // Implementation would depend on your customer segmentation logic
        return [];
    }

    protected function calculateGrowthMetrics(array $current, array $comparison): array
    {
        return [
            'revenue_growth' => $this->calculatePercentageChange($current['total_revenue'], $comparison['total_revenue']),
            'order_growth' => $this->calculatePercentageChange($current['total_orders'], $comparison['total_orders']),
            'aov_growth' => $this->calculatePercentageChange($current['average_order_value'], $comparison['average_order_value']),
            'customer_growth' => $this->calculatePercentageChange($current['unique_customers'], $comparison['unique_customers']),
        ];
    }

    // Add more protected methods for other analytics functions...
    // This is a comprehensive foundation that can be extended further
}