<?php

namespace App\Services;

use App\Models\User;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class CustomerAnalyticsService
{
    public function getUserAnalytics(User $user): array
    {
        $cacheKey = "user_analytics_{$user->id}";
        
        return Cache::remember($cacheKey, 3600, function () use ($user) {
            return [
                'overview' => $this->getUserOverview($user),
                'purchase_history' => $this->getPurchaseHistory($user),
                'product_preferences' => $this->getProductPreferences($user),
                'behavioral_insights' => $this->getBehavioralInsights($user),
                'lifetime_value' => $this->calculateLifetimeValue($user),
                'segmentation' => $this->getCustomerSegmentation($user),
                'engagement_metrics' => $this->getEngagementMetrics($user),
            ];
        });
    }

    protected function getUserOverview(User $user): array
    {
        $orders = $user->orders()->where('status', 'delivered');
        
        return [
            'total_orders' => $user->orders()->count(),
            'completed_orders' => $orders->count(),
            'total_spent' => $orders->sum('total_amount'),
            'average_order_value' => $orders->avg('total_amount') ?? 0,
            'first_order_date' => $user->orders()->oldest()->value('created_at'),
            'last_order_date' => $user->orders()->latest()->value('created_at'),
            'days_since_last_order' => $user->orders()->latest()->first()?->created_at?->diffInDays(now()) ?? null,
            'customer_lifetime_days' => $user->created_at->diffInDays(now()),
        ];
    }

    protected function getPurchaseHistory(User $user): array
    {
        $monthlySpending = $user->orders()
            ->where('status', 'delivered')
            ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, SUM(total_amount) as total')
            ->groupByRaw('YEAR(created_at), MONTH(created_at)')
            ->orderByRaw('YEAR(created_at) DESC, MONTH(created_at) DESC')
            ->limit(12)
            ->get()
            ->map(function ($item) {
                return [
                    'period' => "{$item->year}-{$item->month}",
                    'total' => $item->total,
                ];
            });

        return [
            'monthly_spending' => $monthlySpending,
            'spending_trend' => $this->calculateSpendingTrend($monthlySpending),
            'seasonal_patterns' => $this->getSeasonalPatterns($user),
        ];
    }

    protected function getProductPreferences(User $user): array
    {
        $topCategories = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->where('orders.user_id', $user->id)
            ->where('orders.status', 'delivered')
            ->selectRaw('categories.name, SUM(order_items.quantity) as quantity, SUM(order_items.total_price) as revenue')
            ->groupBy('categories.id', 'categories.name')
            ->orderBy('revenue', 'desc')
            ->limit(10)
            ->get();

        $favoriteProducts = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->where('orders.user_id', $user->id)
            ->where('orders.status', 'delivered')
            ->selectRaw('products.name, products.id, COUNT(*) as purchase_count, SUM(order_items.quantity) as total_quantity')
            ->groupBy('products.id', 'products.name')
            ->orderBy('purchase_count', 'desc')
            ->limit(10)
            ->get();

        return [
            'top_categories' => $topCategories,
            'favorite_products' => $favoriteProducts,
            'brand_loyalty' => $this->getBrandLoyalty($user),
            'price_sensitivity' => $this->calculatePriceSensitivity($user),
        ];
    }

    protected function getBehavioralInsights(User $user): array
    {
        $orders = $user->orders()->where('status', 'delivered');
        
        // Calculate purchase frequency
        $daysBetweenOrders = [];
        $previousOrderDate = null;
        
        foreach ($orders->orderBy('created_at')->get() as $order) {
            if ($previousOrderDate) {
                $daysBetweenOrders[] = $previousOrderDate->diffInDays($order->created_at);
            }
            $previousOrderDate = $order->created_at;
        }

        $avgDaysBetweenOrders = !empty($daysBetweenOrders) ? array_sum($daysBetweenOrders) / count($daysBetweenOrders) : null;

        return [
            'purchase_frequency' => [
                'average_days_between_orders' => $avgDaysBetweenOrders,
                'frequency_category' => $this->categorizePurchaseFrequency($avgDaysBetweenOrders),
            ],
            'order_timing' => $this->getOrderTimingPatterns($user),
            'cart_behavior' => $this->getCartBehavior($user),
            'return_behavior' => $this->getReturnBehavior($user),
        ];
    }

    protected function calculateLifetimeValue(User $user): array
    {
        $totalSpent = $user->orders()->where('status', 'delivered')->sum('total_amount');
        $orderCount = $user->orders()->count();
        $customerLifetimeDays = $user->created_at->diffInDays(now());
        
        $predictedLifetimeValue = $this->predictLifetimeValue($user);
        
        return [
            'historical_value' => $totalSpent,
            'predicted_value' => $predictedLifetimeValue,
            'value_per_day' => $customerLifetimeDays > 0 ? $totalSpent / $customerLifetimeDays : 0,
            'orders_per_year' => $customerLifetimeDays > 0 ? ($orderCount / $customerLifetimeDays) * 365 : 0,
            'clv_segment' => $this->categorizeLifetimeValue($totalSpent),
        ];
    }

    protected function getCustomerSegmentation(User $user): array
    {
        $totalSpent = $user->orders()->where('status', 'delivered')->sum('total_amount');
        $orderCount = $user->orders()->count();
        $daysSinceLastOrder = $user->orders()->latest()->first()?->created_at?->diffInDays(now()) ?? 999;
        
        // RFM Analysis (Recency, Frequency, Monetary)
        $recencyScore = $this->calculateRecencyScore($daysSinceLastOrder);
        $frequencyScore = $this->calculateFrequencyScore($orderCount);
        $monetaryScore = $this->calculateMonetaryScore($totalSpent);
        
        return [
            'rfm_scores' => [
                'recency' => $recencyScore,
                'frequency' => $frequencyScore,
                'monetary' => $monetaryScore,
            ],
            'customer_segment' => $this->determineCustomerSegment($recencyScore, $frequencyScore, $monetaryScore),
            'value_tier' => $this->getValueTier($totalSpent),
            'engagement_level' => $this->getEngagementLevel($user),
        ];
    }

    protected function getEngagementMetrics(User $user): array
    {
        return [
            'account_age_days' => $user->created_at->diffInDays(now()),
            'last_login' => $user->last_login_at,
            'total_reviews' => $user->reviews()->count(),
            'average_review_rating' => $user->reviews()->avg('rating'),
            'wishlist_items' => $user->wishlistItems()->count() ?? 0,
            'referral_count' => $user->referralCodes()->count() ?? 0,
            'social_shares' => 0, // This would be tracked separately
        ];
    }

    // Helper methods

    protected function calculateSpendingTrend($monthlySpending): string
    {
        if ($monthlySpending->count() < 2) {
            return 'insufficient_data';
        }

        $recent = $monthlySpending->take(3)->avg('total');
        $older = $monthlySpending->skip(3)->take(3)->avg('total');
        
        if ($recent > $older * 1.1) {
            return 'increasing';
        } elseif ($recent < $older * 0.9) {
            return 'decreasing';
        } else {
            return 'stable';
        }
    }

    protected function getSeasonalPatterns(User $user): array
    {
        return DB::table('orders')
            ->where('user_id', $user->id)
            ->where('status', 'delivered')
            ->selectRaw('MONTH(created_at) as month, COUNT(*) as order_count, SUM(total_amount) as revenue')
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->month => [
                    'orders' => $item->order_count,
                    'revenue' => $item->revenue,
                ]];
            })
            ->toArray();
    }

    protected function getBrandLoyalty(User $user): array
    {
        // This would require a brands table or brand field in products
        return [
            'favorite_brands' => [],
            'brand_diversity_score' => 0,
        ];
    }

    protected function calculatePriceSensitivity(User $user): string
    {
        $avgOrderValue = $user->orders()->where('status', 'delivered')->avg('total_amount') ?? 0;
        
        if ($avgOrderValue > 2000) {
            return 'low_sensitivity';
        } elseif ($avgOrderValue > 1000) {
            return 'medium_sensitivity';
        } else {
            return 'high_sensitivity';
        }
    }

    protected function categorizePurchaseFrequency($avgDays): string
    {
        if (!$avgDays) return 'new_customer';
        
        if ($avgDays <= 30) return 'very_frequent';
        if ($avgDays <= 90) return 'frequent';
        if ($avgDays <= 180) return 'occasional';
        return 'infrequent';
    }

    protected function getOrderTimingPatterns(User $user): array
    {
        $orders = $user->orders()->selectRaw('HOUR(created_at) as hour, DAYOFWEEK(created_at) as day_of_week, COUNT(*) as count')
            ->groupBy('hour', 'day_of_week')
            ->get();

        return [
            'preferred_hours' => $orders->groupBy('hour')->map(function ($group) {
                return $group->sum('count');
            })->sortDesc()->take(3)->keys()->toArray(),
            'preferred_days' => $orders->groupBy('day_of_week')->map(function ($group) {
                return $group->sum('count');
            })->sortDesc()->take(3)->keys()->toArray(),
        ];
    }

    protected function getCartBehavior(User $user): array
    {
        // This would require cart analytics tracking
        return [
            'average_cart_abandonment' => 0,
            'average_items_per_cart' => 0,
        ];
    }

    protected function getReturnBehavior(User $user): array
    {
        // This would require returns tracking
        return [
            'return_rate' => 0,
            'most_returned_categories' => [],
        ];
    }

    protected function predictLifetimeValue(User $user): float
    {
        // Simple prediction based on current spending pattern
        $totalSpent = $user->orders()->where('status', 'delivered')->sum('total_amount') ?? 0;
        $customerLifetimeDays = $user->created_at->diffInDays(now());
        $orderCount = $user->orders()->count();
        
        if ($customerLifetimeDays == 0 || $orderCount == 0) {
            return 0;
        }
        
        $dailyValue = $totalSpent / $customerLifetimeDays;
        $predictedLifetimeDays = 365 * 2; // Assume 2-year customer lifetime
        
        return $dailyValue * $predictedLifetimeDays;
    }

    protected function categorizeLifetimeValue(float $value): string
    {
        if ($value >= 50000) return 'high_value';
        if ($value >= 20000) return 'medium_high_value';
        if ($value >= 10000) return 'medium_value';
        if ($value >= 5000) return 'low_medium_value';
        return 'low_value';
    }

    protected function calculateRecencyScore(int $daysSinceLastOrder): int
    {
        if ($daysSinceLastOrder <= 30) return 5;
        if ($daysSinceLastOrder <= 90) return 4;
        if ($daysSinceLastOrder <= 180) return 3;
        if ($daysSinceLastOrder <= 365) return 2;
        return 1;
    }

    protected function calculateFrequencyScore(int $orderCount): int
    {
        if ($orderCount >= 20) return 5;
        if ($orderCount >= 10) return 4;
        if ($orderCount >= 5) return 3;
        if ($orderCount >= 2) return 2;
        return 1;
    }

    protected function calculateMonetaryScore(float $totalSpent): int
    {
        if ($totalSpent >= 50000) return 5;
        if ($totalSpent >= 25000) return 4;
        if ($totalSpent >= 10000) return 3;
        if ($totalSpent >= 5000) return 2;
        return 1;
    }

    protected function determineCustomerSegment(int $r, int $f, int $m): string
    {
        $score = ($r * 100) + ($f * 10) + $m;
        
        if ($score >= 555) return 'champions';
        if ($score >= 454) return 'loyal_customers';
        if ($score >= 344) return 'potential_loyalists';
        if ($score >= 334) return 'promising';
        if ($score >= 313) return 'customers_needing_attention';
        if ($score >= 233) return 'about_to_sleep';
        if ($score >= 155) return 'at_risk';
        if ($score >= 144) return 'cannot_lose_them';
        if ($score >= 111) return 'hibernating';
        return 'lost';
    }

    protected function getValueTier(float $totalSpent): string
    {
        if ($totalSpent >= 100000) return 'platinum';
        if ($totalSpent >= 50000) return 'gold';
        if ($totalSpent >= 25000) return 'silver';
        if ($totalSpent >= 10000) return 'bronze';
        return 'standard';
    }

    protected function getEngagementLevel(User $user): string
    {
        $score = 0;
        
        // Recent activity
        if ($user->last_login_at && $user->last_login_at->diffInDays(now()) <= 7) $score += 2;
        if ($user->orders()->where('created_at', '>=', now()->subDays(30))->exists()) $score += 2;
        if ($user->reviews()->exists()) $score += 1;
        
        if ($score >= 4) return 'highly_engaged';
        if ($score >= 2) return 'moderately_engaged';
        return 'low_engaged';
    }
}