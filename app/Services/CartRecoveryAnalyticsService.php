<?php

namespace App\Services;

use App\Models\PersistentCart;
use App\Models\CartRecoveryDiscount;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CartRecoveryAnalyticsService
{
    /**
     * Get comprehensive recovery analytics
     */
    public function getAnalytics($dateFrom = null, $dateTo = null)
    {
        $dateFrom = $dateFrom ? Carbon::parse($dateFrom) : now()->subDays(30);
        $dateTo = $dateTo ? Carbon::parse($dateTo) : now();

        return [
            'summary' => $this->getSummaryMetrics($dateFrom, $dateTo),
            'by_segment' => $this->getMetricsBySegment($dateFrom, $dateTo),
            'by_device' => $this->getMetricsByDevice($dateFrom, $dateTo),
            'email_performance' => $this->getEmailPerformance($dateFrom, $dateTo),
            'conversion_funnel' => $this->getConversionFunnel($dateFrom, $dateTo),
            'revenue' => $this->getRevenueMetrics($dateFrom, $dateTo),
            'trends' => $this->getTrends($dateFrom, $dateTo),
        ];
    }

    /**
     * Get summary metrics
     */
    private function getSummaryMetrics($dateFrom, $dateTo)
    {
        $abandoned = PersistentCart::where('is_abandoned', true)
            ->whereBetween('abandoned_at', [$dateFrom, $dateTo])
            ->count();

        $recovered = PersistentCart::where('status', 'recovered')
            ->whereBetween('updated_at', [$dateFrom, $dateTo])
            ->count();

        $expired = PersistentCart::where('status', 'expired')
            ->whereBetween('updated_at', [$dateFrom, $dateTo])
            ->count();

        $recoveryRate = $abandoned > 0 ? round(($recovered / $abandoned) * 100, 2) : 0;

        $totalAbandonedValue = PersistentCart::where('is_abandoned', true)
            ->whereBetween('abandoned_at', [$dateFrom, $dateTo])
            ->sum('total_amount');

        $recoveredValue = PersistentCart::where('status', 'recovered')
            ->whereBetween('updated_at', [$dateFrom, $dateTo])
            ->sum('total_amount');

        $emailsSent = PersistentCart::where('is_abandoned', true)
            ->whereBetween('abandoned_at', [$dateFrom, $dateTo])
            ->where('recovery_email_count', '>', 0)
            ->count();

        return [
            'total_abandoned' => $abandoned,
            'total_recovered' => $recovered,
            'total_expired' => $expired,
            'recovery_rate' => $recoveryRate,
            'recovery_rate_formatted' => "{$recoveryRate}%",
            'abandoned_value' => round($totalAbandonedValue, 2),
            'recovered_value' => round($recoveredValue, 2),
            'value_recovery_rate' => $totalAbandonedValue > 0
                ? round(($recoveredValue / $totalAbandonedValue) * 100, 2)
                : 0,
            'emails_sent' => $emailsSent,
            'average_recovery_time' => $this->getAverageRecoveryTime($dateFrom, $dateTo),
        ];
    }

    /**
     * Get metrics by customer segment
     */
    private function getMetricsBySegment($dateFrom, $dateTo)
    {
        $segments = ['vip', 'high_value', 'repeat', 'regular'];
        $result = [];

        foreach ($segments as $segment) {
            $abandoned = PersistentCart::where('customer_segment', $segment)
                ->where('is_abandoned', true)
                ->whereBetween('abandoned_at', [$dateFrom, $dateTo])
                ->count();

            $recovered = PersistentCart::where('customer_segment', $segment)
                ->where('status', 'recovered')
                ->whereBetween('updated_at', [$dateFrom, $dateTo])
                ->count();

            $recoveryRate = $abandoned > 0 ? round(($recovered / $abandoned) * 100, 2) : 0;

            $result[$segment] = [
                'abandoned' => $abandoned,
                'recovered' => $recovered,
                'recovery_rate' => $recoveryRate,
                'value' => round(PersistentCart::where('customer_segment', $segment)
                    ->where('is_abandoned', true)
                    ->whereBetween('abandoned_at', [$dateFrom, $dateTo])
                    ->sum('total_amount'), 2),
            ];
        }

        return $result;
    }

    /**
     * Get metrics by device type
     */
    private function getMetricsByDevice($dateFrom, $dateTo)
    {
        $devices = ['mobile', 'desktop', 'tablet'];
        $result = [];

        foreach ($devices as $device) {
            $abandoned = PersistentCart::where('device_type', $device)
                ->where('is_abandoned', true)
                ->whereBetween('abandoned_at', [$dateFrom, $dateTo])
                ->count();

            $recovered = PersistentCart::where('device_type', $device)
                ->where('status', 'recovered')
                ->whereBetween('updated_at', [$dateFrom, $dateTo])
                ->count();

            $recoveryRate = $abandoned > 0 ? round(($recovered / $abandoned) * 100, 2) : 0;

            $result[$device] = [
                'abandoned' => $abandoned,
                'recovered' => $recovered,
                'recovery_rate' => $recoveryRate,
            ];
        }

        return $result;
    }

    /**
     * Get email performance metrics
     */
    private function getEmailPerformance($dateFrom, $dateTo)
    {
        $noEmail = PersistentCart::where('is_abandoned', true)
            ->whereBetween('abandoned_at', [$dateFrom, $dateTo])
            ->where('recovery_email_count', 0)
            ->count();

        $oneEmail = PersistentCart::where('is_abandoned', true)
            ->whereBetween('abandoned_at', [$dateFrom, $dateTo])
            ->where('recovery_email_count', 1)
            ->count();

        $twoEmails = PersistentCart::where('is_abandoned', true)
            ->whereBetween('abandoned_at', [$dateFrom, $dateTo])
            ->where('recovery_email_count', 2)
            ->count();

        $threeOrMore = PersistentCart::where('is_abandoned', true)
            ->whereBetween('abandoned_at', [$dateFrom, $dateTo])
            ->where('recovery_email_count', '>=', 3)
            ->count();

        $total = $noEmail + $oneEmail + $twoEmails + $threeOrMore;

        return [
            'no_email' => [
                'count' => $noEmail,
                'percentage' => $total > 0 ? round(($noEmail / $total) * 100, 2) : 0,
            ],
            'one_email' => [
                'count' => $oneEmail,
                'percentage' => $total > 0 ? round(($oneEmail / $total) * 100, 2) : 0,
            ],
            'two_emails' => [
                'count' => $twoEmails,
                'percentage' => $total > 0 ? round(($twoEmails / $total) * 100, 2) : 0,
            ],
            'three_or_more' => [
                'count' => $threeOrMore,
                'percentage' => $total > 0 ? round(($threeOrMore / $total) * 100, 2) : 0,
            ],
        ];
    }

    /**
     * Get conversion funnel
     */
    private function getConversionFunnel($dateFrom, $dateTo)
    {
        $abandoned = PersistentCart::where('is_abandoned', true)
            ->whereBetween('abandoned_at', [$dateFrom, $dateTo])
            ->count();

        $emailedOnce = PersistentCart::where('is_abandoned', true)
            ->whereBetween('abandoned_at', [$dateFrom, $dateTo])
            ->where('recovery_email_count', '>=', 1)
            ->count();

        $emailedTwice = PersistentCart::where('is_abandoned', true)
            ->whereBetween('abandoned_at', [$dateFrom, $dateTo])
            ->where('recovery_email_count', '>=', 2)
            ->count();

        $emailedThrice = PersistentCart::where('is_abandoned', true)
            ->whereBetween('abandoned_at', [$dateFrom, $dateTo])
            ->where('recovery_email_count', '>=', 3)
            ->count();

        $recovered = PersistentCart::where('status', 'recovered')
            ->whereBetween('updated_at', [$dateFrom, $dateTo])
            ->count();

        return [
            'step_1_abandoned' => [
                'count' => $abandoned,
                'conversion_rate' => '100%',
            ],
            'step_2_first_email' => [
                'count' => $emailedOnce,
                'conversion_rate' => $abandoned > 0 ? round(($emailedOnce / $abandoned) * 100, 2) . '%' : '0%',
            ],
            'step_3_second_email' => [
                'count' => $emailedTwice,
                'conversion_rate' => $abandoned > 0 ? round(($emailedTwice / $abandoned) * 100, 2) . '%' : '0%',
            ],
            'step_4_third_email' => [
                'count' => $emailedThrice,
                'conversion_rate' => $abandoned > 0 ? round(($emailedThrice / $abandoned) * 100, 2) . '%' : '0%',
            ],
            'step_5_recovered' => [
                'count' => $recovered,
                'conversion_rate' => $abandoned > 0 ? round(($recovered / $abandoned) * 100, 2) . '%' : '0%',
            ],
        ];
    }

    /**
     * Get revenue metrics
     */
    private function getRevenueMetrics($dateFrom, $dateTo)
    {
        $recoveredOrders = PersistentCart::where('status', 'recovered')
            ->whereBetween('updated_at', [$dateFrom, $dateTo])
            ->sum('total_amount');

        $discountUsed = CartRecoveryDiscount::where('is_used', true)
            ->whereBetween('used_at', [$dateFrom, $dateTo])
            ->sum('revenue_generated');

        $discountValue = CartRecoveryDiscount::where('is_used', true)
            ->whereBetween('used_at', [$dateFrom, $dateTo])
            ->sum(DB::raw('CASE
                WHEN type = "percentage" THEN (value * revenue_generated / 100)
                ELSE value
            END'));

        return [
            'recovered_value' => round($recoveredOrders, 2),
            'discount_issued' => round($discountValue, 2),
            'net_revenue' => round($recoveredOrders - $discountValue, 2),
            'roi' => round($recoveredOrders > 0 ? (($recoveredOrders - $discountValue) / $recoveredOrders) * 100 : 0, 2),
            'average_order_value' => round($recoveredOrders > 0 ? $recoveredOrders / max(1, PersistentCart::where('status', 'recovered')
                ->whereBetween('updated_at', [$dateFrom, $dateTo])
                ->count()) : 0, 2),
        ];
    }

    /**
     * Get trends over time
     */
    private function getTrends($dateFrom, $dateTo)
    {
        $days = $dateTo->diffInDays($dateFrom);
        $trends = [];

        for ($i = $days; $i >= 0; $i--) {
            $date = $dateTo->copy()->subDays($i);
            $dateStr = $date->format('Y-m-d');

            $abandoned = PersistentCart::where('is_abandoned', true)
                ->whereDate('abandoned_at', $dateStr)
                ->count();

            $recovered = PersistentCart::where('status', 'recovered')
                ->whereDate('updated_at', $dateStr)
                ->count();

            $value = PersistentCart::where('is_abandoned', true)
                ->whereDate('abandoned_at', $dateStr)
                ->sum('total_amount');

            $trends[$dateStr] = [
                'abandoned' => $abandoned,
                'recovered' => $recovered,
                'value' => round($value, 2),
                'recovery_rate' => $abandoned > 0 ? round(($recovered / $abandoned) * 100, 2) : 0,
            ];
        }

        return $trends;
    }

    /**
     * Get average time to recovery
     */
    private function getAverageRecoveryTime($dateFrom, $dateTo)
    {
        $avgSeconds = PersistentCart::where('status', 'recovered')
            ->whereBetween('updated_at', [$dateFrom, $dateTo])
            ->selectRaw('AVG(UNIX_TIMESTAMP(updated_at) - UNIX_TIMESTAMP(abandoned_at)) as avg_seconds')
            ->first()?->avg_seconds ?? 0;

        $hours = round($avgSeconds / 3600, 2);

        return [
            'hours' => $hours,
            'formatted' => "{$hours} hours",
        ];
    }

    /**
     * Get segment comparison
     */
    public function getSegmentComparison($dateFrom = null, $dateTo = null)
    {
        $dateFrom = $dateFrom ? Carbon::parse($dateFrom) : now()->subDays(30);
        $dateTo = $dateTo ? Carbon::parse($dateTo) : now();

        return $this->getMetricsBySegment($dateFrom, $dateTo);
    }

    /**
     * Get discount code effectiveness
     */
    public function getDiscountEffectiveness($dateFrom = null, $dateTo = null)
    {
        $dateFrom = $dateFrom ? Carbon::parse($dateFrom) : now()->subDays(30);
        $dateTo = $dateTo ? Carbon::parse($dateTo) : now();

        $secondEmailDiscount = CartRecoveryDiscount::where('value', '<=', 5)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->get();

        $finalEmailDiscount = CartRecoveryDiscount::where('value', '>', 5)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->get();

        return [
            'second_email' => [
                'generated' => $secondEmailDiscount->count(),
                'used' => $secondEmailDiscount->where('is_used', true)->count(),
                'usage_rate' => $secondEmailDiscount->count() > 0
                    ? round(($secondEmailDiscount->where('is_used', true)->count() / $secondEmailDiscount->count()) * 100, 2)
                    : 0,
                'total_revenue' => round($secondEmailDiscount->where('is_used', true)->sum('revenue_generated'), 2),
            ],
            'final_email' => [
                'generated' => $finalEmailDiscount->count(),
                'used' => $finalEmailDiscount->where('is_used', true)->count(),
                'usage_rate' => $finalEmailDiscount->count() > 0
                    ? round(($finalEmailDiscount->where('is_used', true)->count() / $finalEmailDiscount->count()) * 100, 2)
                    : 0,
                'total_revenue' => round($finalEmailDiscount->where('is_used', true)->sum('revenue_generated'), 2),
            ],
        ];
    }
}
