<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BundleAnalyticsController extends Controller
{
    /**
     * Get bundle analytics overview
     */
    public function index(Request $request)
    {
        $query = DB::table('bundle_analytics');

        // Search by product IDs
        if ($request->filled('product_id')) {
            $productId = $request->product_id;
            $query->whereRaw("JSON_CONTAINS(product_ids, ?)", [json_encode([$productId])]);
        }

        // Filter by minimum views
        if ($request->filled('min_views')) {
            $query->where('views', '>=', $request->min_views);
        }

        // Filter by minimum conversion rate
        if ($request->filled('min_conversion_rate')) {
            $query->where('conversion_rate', '>=', $request->min_conversion_rate);
        }

        // Sort
        $sortBy = $request->get('sort_by', 'views');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 20);
        $bundles = $query->paginate($perPage);

        // Decode product_ids and load products for each bundle
        $bundles->getCollection()->transform(function ($bundle) {
            $productIds = json_decode($bundle->product_ids, true);
            $bundle->product_ids_array = $productIds;
            $bundle->products = Product::whereIn('id', $productIds)->get(['id', 'name', 'sku', 'price']);
            return $bundle;
        });

        return response()->json([
            'success' => true,
            'bundles' => $bundles
        ]);
    }

    /**
     * Get overall bundle analytics statistics
     */
    public function statistics()
    {
        try {
            $stats = [
                'total_bundles' => DB::table('bundle_analytics')->count(),
                'total_views' => DB::table('bundle_analytics')->sum('views'),
                'total_add_to_cart' => DB::table('bundle_analytics')->sum('add_to_cart'),
                'total_purchases' => DB::table('bundle_analytics')->sum('purchases'),
                'total_revenue' => DB::table('bundle_analytics')->sum('total_revenue'),
                'average_conversion_rate' => round(DB::table('bundle_analytics')->avg('conversion_rate'), 2),
            ];

            // Calculate overall funnel metrics
            if ($stats['total_views'] > 0) {
                $stats['view_to_cart_rate'] = round(($stats['total_add_to_cart'] / $stats['total_views']) * 100, 2);
                $stats['overall_conversion_rate'] = round(($stats['total_purchases'] / $stats['total_views']) * 100, 2);
            } else {
                $stats['view_to_cart_rate'] = 0;
                $stats['overall_conversion_rate'] = 0;
            }

            if ($stats['total_add_to_cart'] > 0) {
                $stats['cart_to_purchase_rate'] = round(($stats['total_purchases'] / $stats['total_add_to_cart']) * 100, 2);
            } else {
                $stats['cart_to_purchase_rate'] = 0;
            }

            if ($stats['total_purchases'] > 0) {
                $stats['average_order_value'] = round($stats['total_revenue'] / $stats['total_purchases'], 2);
            } else {
                $stats['average_order_value'] = 0;
            }

            return response()->json([
                'success' => true,
                'statistics' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get top performing bundles
     */
    public function topBundles(Request $request)
    {
        $metric = $request->get('metric', 'conversion_rate'); // views, add_to_cart, purchases, conversion_rate, total_revenue
        $limit = $request->get('limit', 10);

        $bundles = DB::table('bundle_analytics')
            ->orderBy($metric, 'desc')
            ->limit($limit)
            ->get();

        // Load products for each bundle
        $bundles->transform(function ($bundle) {
            $productIds = json_decode($bundle->product_ids, true);
            $bundle->product_ids_array = $productIds;
            $bundle->products = Product::whereIn('id', $productIds)->get(['id', 'name', 'sku', 'price']);
            return $bundle;
        });

        return response()->json([
            'success' => true,
            'metric' => $metric,
            'bundles' => $bundles
        ]);
    }

    /**
     * Get bundle performance over time
     */
    public function performance(Request $request)
    {
        $bundleId = $request->get('bundle_id');
        $period = $request->get('period', '30'); // days

        if (!$bundleId) {
            return response()->json([
                'success' => false,
                'message' => 'bundle_id is required'
            ], 400);
        }

        try {
            $bundle = DB::table('bundle_analytics')
                ->where('bundle_id', $bundleId)
                ->first();

            if (!$bundle) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bundle not found'
                ], 404);
            }

            // Load products
            $productIds = json_decode($bundle->product_ids, true);
            $products = Product::whereIn('id', $productIds)->get(['id', 'name', 'sku', 'price']);

            // Calculate metrics
            $metrics = [
                'views' => $bundle->views,
                'clicks' => $bundle->clicks,
                'add_to_cart' => $bundle->add_to_cart,
                'purchases' => $bundle->purchases,
                'total_revenue' => $bundle->total_revenue,
                'conversion_rate' => $bundle->conversion_rate,
                'view_to_cart_rate' => $bundle->views > 0
                    ? round(($bundle->add_to_cart / $bundle->views) * 100, 2)
                    : 0,
                'cart_to_purchase_rate' => $bundle->add_to_cart > 0
                    ? round(($bundle->purchases / $bundle->add_to_cart) * 100, 2)
                    : 0,
                'average_order_value' => $bundle->purchases > 0
                    ? round($bundle->total_revenue / $bundle->purchases, 2)
                    : 0,
            ];

            return response()->json([
                'success' => true,
                'bundle_id' => $bundleId,
                'products' => $products,
                'metrics' => $metrics,
                'last_updated' => $bundle->updated_at
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get performance data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get bundle funnel analysis
     */
    public function funnel()
    {
        try {
            $totalBundles = DB::table('bundle_analytics')->count();

            $funnelData = [
                'total_bundles' => $totalBundles,
                'bundles_with_views' => DB::table('bundle_analytics')
                    ->where('views', '>', 0)
                    ->count(),
                'bundles_with_add_to_cart' => DB::table('bundle_analytics')
                    ->where('add_to_cart', '>', 0)
                    ->count(),
                'bundles_with_purchases' => DB::table('bundle_analytics')
                    ->where('purchases', '>', 0)
                    ->count(),
            ];

            // Calculate funnel drop-off rates
            if ($funnelData['bundles_with_views'] > 0) {
                $funnelData['view_to_cart_drop_rate'] = round(
                    (1 - ($funnelData['bundles_with_add_to_cart'] / $funnelData['bundles_with_views'])) * 100,
                    2
                );
            }

            if ($funnelData['bundles_with_add_to_cart'] > 0) {
                $funnelData['cart_to_purchase_drop_rate'] = round(
                    (1 - ($funnelData['bundles_with_purchases'] / $funnelData['bundles_with_add_to_cart'])) * 100,
                    2
                );
            }

            // Get aggregate metrics
            $aggregates = DB::table('bundle_analytics')
                ->selectRaw('
                    SUM(views) as total_views,
                    SUM(add_to_cart) as total_add_to_cart,
                    SUM(purchases) as total_purchases
                ')
                ->first();

            $funnelData['metrics'] = [
                'total_views' => $aggregates->total_views ?? 0,
                'total_add_to_cart' => $aggregates->total_add_to_cart ?? 0,
                'total_purchases' => $aggregates->total_purchases ?? 0,
            ];

            if ($aggregates->total_views > 0) {
                $funnelData['metrics']['view_to_cart_rate'] = round(
                    ($aggregates->total_add_to_cart / $aggregates->total_views) * 100,
                    2
                );
                $funnelData['metrics']['overall_conversion_rate'] = round(
                    ($aggregates->total_purchases / $aggregates->total_views) * 100,
                    2
                );
            }

            return response()->json([
                'success' => true,
                'funnel' => $funnelData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get funnel data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get product bundle participation
     */
    public function productParticipation($productId)
    {
        try {
            $product = Product::findOrFail($productId);

            // Find all bundles containing this product
            $bundles = DB::table('bundle_analytics')
                ->whereRaw("JSON_CONTAINS(product_ids, ?)", [json_encode([$productId])])
                ->get();

            $totalViews = $bundles->sum('views');
            $totalAddToCart = $bundles->sum('add_to_cart');
            $totalPurchases = $bundles->sum('purchases');
            $totalRevenue = $bundles->sum('total_revenue');

            // Load products for each bundle
            $bundles->transform(function ($bundle) use ($productId) {
                $productIds = json_decode($bundle->product_ids, true);
                $bundle->product_ids_array = $productIds;
                $bundle->products = Product::whereIn('id', $productIds)->get(['id', 'name', 'sku', 'price']);
                $bundle->is_main_product = $productIds[0] == $productId;
                return $bundle;
            });

            return response()->json([
                'success' => true,
                'product' => $product,
                'bundle_count' => $bundles->count(),
                'total_views' => $totalViews,
                'total_add_to_cart' => $totalAddToCart,
                'total_purchases' => $totalPurchases,
                'total_revenue' => round($totalRevenue, 2),
                'bundles' => $bundles
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get product participation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export bundle analytics data
     */
    public function export(Request $request)
    {
        try {
            $format = $request->get('format', 'json'); // json, csv

            $bundles = DB::table('bundle_analytics')
                ->orderBy('views', 'desc')
                ->get();

            // Load products for each bundle
            $bundles->transform(function ($bundle) {
                $productIds = json_decode($bundle->product_ids, true);
                $bundle->product_ids_array = $productIds;
                $bundle->products = Product::whereIn('id', $productIds)->get(['id', 'name', 'sku']);
                $bundle->product_names = $bundle->products->pluck('name')->implode(', ');
                return $bundle;
            });

            if ($format === 'csv') {
                $headers = [
                    'Content-Type' => 'text/csv',
                    'Content-Disposition' => 'attachment; filename="bundle_analytics_' . date('Y-m-d') . '.csv"',
                ];

                $callback = function() use ($bundles) {
                    $file = fopen('php://output', 'w');

                    // CSV headers
                    fputcsv($file, [
                        'Bundle ID',
                        'Products',
                        'Views',
                        'Clicks',
                        'Add to Cart',
                        'Purchases',
                        'Total Revenue',
                        'Conversion Rate (%)',
                        'Last Updated'
                    ]);

                    // CSV rows
                    foreach ($bundles as $bundle) {
                        fputcsv($file, [
                            $bundle->bundle_id,
                            $bundle->product_names,
                            $bundle->views,
                            $bundle->clicks,
                            $bundle->add_to_cart,
                            $bundle->purchases,
                            number_format($bundle->total_revenue, 2),
                            number_format($bundle->conversion_rate, 2),
                            $bundle->updated_at
                        ]);
                    }

                    fclose($file);
                };

                return response()->stream($callback, 200, $headers);
            }

            // JSON format
            return response()->json([
                'success' => true,
                'bundles' => $bundles,
                'exported_at' => now()->toDateTimeString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear bundle analytics data
     */
    public function clear(Request $request)
    {
        try {
            $bundleId = $request->get('bundle_id');

            if ($bundleId) {
                // Clear specific bundle
                DB::table('bundle_analytics')
                    ->where('bundle_id', $bundleId)
                    ->delete();

                return response()->json([
                    'success' => true,
                    'message' => 'Bundle analytics cleared successfully'
                ]);
            } else {
                // Clear all analytics
                $count = DB::table('bundle_analytics')->delete();

                return response()->json([
                    'success' => true,
                    'message' => "{$count} bundle analytics records cleared successfully"
                ]);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear analytics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get comparison between bundles
     */
    public function compare(Request $request)
    {
        $bundleIds = $request->get('bundle_ids', []);

        if (empty($bundleIds) || !is_array($bundleIds)) {
            return response()->json([
                'success' => false,
                'message' => 'bundle_ids array is required'
            ], 400);
        }

        try {
            $bundles = DB::table('bundle_analytics')
                ->whereIn('bundle_id', $bundleIds)
                ->get();

            // Load products for each bundle
            $bundles->transform(function ($bundle) {
                $productIds = json_decode($bundle->product_ids, true);
                $bundle->product_ids_array = $productIds;
                $bundle->products = Product::whereIn('id', $productIds)->get(['id', 'name', 'sku', 'price']);

                // Calculate additional metrics
                $bundle->view_to_cart_rate = $bundle->views > 0
                    ? round(($bundle->add_to_cart / $bundle->views) * 100, 2)
                    : 0;
                $bundle->cart_to_purchase_rate = $bundle->add_to_cart > 0
                    ? round(($bundle->purchases / $bundle->add_to_cart) * 100, 2)
                    : 0;
                $bundle->average_order_value = $bundle->purchases > 0
                    ? round($bundle->total_revenue / $bundle->purchases, 2)
                    : 0;

                return $bundle;
            });

            return response()->json([
                'success' => true,
                'bundles' => $bundles,
                'comparison' => [
                    'best_views' => $bundles->sortByDesc('views')->first()->bundle_id ?? null,
                    'best_conversion' => $bundles->sortByDesc('conversion_rate')->first()->bundle_id ?? null,
                    'best_revenue' => $bundles->sortByDesc('total_revenue')->first()->bundle_id ?? null,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to compare bundles: ' . $e->getMessage()
            ], 500);
        }
    }
}
