<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PaymentAnalyticsController extends Controller
{
    /**
     * Get payment analytics summary (KPIs)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function summary(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->subDays(30)->startOfDay());
        $endDate = $request->input('end_date', Carbon::now()->endOfDay());

        // Convert to Carbon instances if they're strings
        $startDate = Carbon::parse($startDate);
        $endDate = Carbon::parse($endDate);

        // Total Revenue
        $totalRevenue = Order::whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('payment_status', ['paid', 'success', 'completed'])
            ->sum('total');

        // Total Transactions
        $totalTransactions = Order::whereBetween('created_at', [$startDate, $endDate])
            ->count();

        // Successful Transactions
        $successfulTransactions = Order::whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('payment_status', ['paid', 'success', 'completed'])
            ->count();

        // Success Rate
        $successRate = $totalTransactions > 0
            ? round(($successfulTransactions / $totalTransactions) * 100, 2)
            : 0;

        // Average Order Value
        $avgOrderValue = $successfulTransactions > 0
            ? round($totalRevenue / $successfulTransactions, 2)
            : 0;

        // Failed Transactions
        $failedTransactions = Order::whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('payment_status', ['failed', 'cancelled'])
            ->count();

        // Pending Transactions
        $pendingTransactions = Order::whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('payment_status', ['pending', 'processing'])
            ->count();

        // COD Orders Count
        $codOrders = Order::whereBetween('created_at', [$startDate, $endDate])
            ->where('payment_method', 'cod')
            ->count();

        // Online Payment Orders Count
        $onlineOrders = Order::whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull('payment_method')
            ->where('payment_method', '!=', 'cod')
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_revenue' => $totalRevenue,
                'total_transactions' => $totalTransactions,
                'successful_transactions' => $successfulTransactions,
                'failed_transactions' => $failedTransactions,
                'pending_transactions' => $pendingTransactions,
                'success_rate' => $successRate,
                'avg_order_value' => $avgOrderValue,
                'cod_orders' => $codOrders,
                'online_orders' => $onlineOrders,
                'period' => [
                    'start' => $startDate->toDateTimeString(),
                    'end' => $endDate->toDateTimeString()
                ]
            ]
        ]);
    }

    /**
     * Get revenue trend over time
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function revenueTrend(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->subDays(30)->startOfDay());
        $endDate = $request->input('end_date', Carbon::now()->endOfDay());
        $groupBy = $request->input('group_by', 'day'); // day, week, month

        $startDate = Carbon::parse($startDate);
        $endDate = Carbon::parse($endDate);

        // Determine date format and grouping based on period
        $dateFormat = match($groupBy) {
            'hour' => '%Y-%m-%d %H:00',
            'day' => '%Y-%m-%d',
            'week' => '%Y-%u', // Year-Week
            'month' => '%Y-%m',
            'year' => '%Y',
            default => '%Y-%m-%d'
        };

        $revenue = Order::select(
            DB::raw("DATE_FORMAT(created_at, '{$dateFormat}') as period"),
            DB::raw('SUM(total) as revenue'),
            DB::raw('COUNT(*) as transaction_count')
        )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('payment_status', ['paid', 'success', 'completed'])
            ->groupBy('period')
            ->orderBy('period', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $revenue,
            'period' => [
                'start' => $startDate->toDateTimeString(),
                'end' => $endDate->toDateTimeString(),
                'group_by' => $groupBy
            ]
        ]);
    }

    /**
     * Get payment method distribution
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function methodDistribution(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->subDays(30)->startOfDay());
        $endDate = $request->input('end_date', Carbon::now()->endOfDay());

        $startDate = Carbon::parse($startDate);
        $endDate = Carbon::parse($endDate);

        $distribution = Order::select(
            'payment_method',
            DB::raw('COUNT(*) as count'),
            DB::raw('SUM(total) as revenue'),
            DB::raw('ROUND(AVG(total), 2) as avg_order_value')
        )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull('payment_method')
            ->groupBy('payment_method')
            ->orderBy('count', 'desc')
            ->get();

        // Calculate percentages
        $totalCount = $distribution->sum('count');
        $distribution = $distribution->map(function ($item) use ($totalCount) {
            $item->percentage = $totalCount > 0
                ? round(($item->count / $totalCount) * 100, 2)
                : 0;
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $distribution,
            'period' => [
                'start' => $startDate->toDateTimeString(),
                'end' => $endDate->toDateTimeString()
            ]
        ]);
    }

    /**
     * Get gateway performance comparison
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function gatewayPerformance(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->subDays(30)->startOfDay());
        $endDate = $request->input('end_date', Carbon::now()->endOfDay());

        $startDate = Carbon::parse($startDate);
        $endDate = Carbon::parse($endDate);

        $performance = Order::select(
            'payment_method as gateway',
            DB::raw('COUNT(*) as total_transactions'),
            DB::raw('SUM(CASE WHEN payment_status IN ("paid", "success", "completed") THEN 1 ELSE 0 END) as successful_transactions'),
            DB::raw('SUM(CASE WHEN payment_status IN ("failed", "cancelled") THEN 1 ELSE 0 END) as failed_transactions'),
            DB::raw('SUM(CASE WHEN payment_status IN ("pending", "processing") THEN 1 ELSE 0 END) as pending_transactions'),
            DB::raw('SUM(CASE WHEN payment_status IN ("paid", "success", "completed") THEN total ELSE 0 END) as revenue')
        )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull('payment_method')
            ->groupBy('payment_method')
            ->orderBy('total_transactions', 'desc')
            ->get();

        // Calculate success rates
        $performance = $performance->map(function ($item) {
            $item->success_rate = $item->total_transactions > 0
                ? round(($item->successful_transactions / $item->total_transactions) * 100, 2)
                : 0;
            $item->failure_rate = $item->total_transactions > 0
                ? round(($item->failed_transactions / $item->total_transactions) * 100, 2)
                : 0;
            $item->avg_transaction_value = $item->successful_transactions > 0
                ? round($item->revenue / $item->successful_transactions, 2)
                : 0;
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $performance,
            'period' => [
                'start' => $startDate->toDateTimeString(),
                'end' => $endDate->toDateTimeString()
            ]
        ]);
    }

    /**
     * Get recent failed payments
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function recentFailedPayments(Request $request)
    {
        $limit = $request->input('limit', 20);

        $failedPayments = Order::select([
            'id',
            'order_number',
            'user_id',
            'payment_method',
            'payment_status',
            'total',
            'created_at',
            'updated_at'
        ])
            ->with('user:id,name,email')
            ->whereIn('payment_status', ['failed', 'cancelled'])
            ->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $failedPayments
        ]);
    }

    /**
     * Get payment status distribution
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function statusDistribution(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->subDays(30)->startOfDay());
        $endDate = $request->input('end_date', Carbon::now()->endOfDay());

        $startDate = Carbon::parse($startDate);
        $endDate = Carbon::parse($endDate);

        $distribution = Order::select(
            'payment_status',
            DB::raw('COUNT(*) as count'),
            DB::raw('SUM(total) as total_amount')
        )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('payment_status')
            ->orderBy('count', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $distribution,
            'period' => [
                'start' => $startDate->toDateTimeString(),
                'end' => $endDate->toDateTimeString()
            ]
        ]);
    }
}


