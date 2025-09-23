<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CustomerAnalytics extends Model
{
    use HasFactory;

    protected $table = 'customer_analytics';

    protected $fillable = [
        'user_id',
        'total_orders',
        'total_spent',
        'average_order_value',
        'first_order_date',
        'last_order_date',
        'total_reviews',
        'average_rating',
        'referral_count',
        'loyalty_points',
        'customer_lifetime_value',
        'repeat_purchase_rate',
        'cart_abandonment_count',
        'favorite_categories',
        'purchase_frequency',
        'last_activity_at',
        'segment',
        'preferences',
        'metrics'
    ];

    protected $casts = [
        'total_orders' => 'integer',
        'total_spent' => 'decimal:2',
        'average_order_value' => 'decimal:2',
        'first_order_date' => 'datetime',
        'last_order_date' => 'datetime',
        'total_reviews' => 'integer',
        'average_rating' => 'decimal:2',
        'referral_count' => 'integer',
        'loyalty_points' => 'integer',
        'customer_lifetime_value' => 'decimal:2',
        'repeat_purchase_rate' => 'decimal:2',
        'cart_abandonment_count' => 'integer',
        'favorite_categories' => 'array',
        'purchase_frequency' => 'decimal:2',
        'last_activity_at' => 'datetime',
        'preferences' => 'array',
        'metrics' => 'array'
    ];

    /**
     * Get the user that owns the analytics.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to get high-value customers
     */
    public function scopeHighValue($query)
    {
        return $query->where('customer_lifetime_value', '>', 10000);
    }

    /**
     * Scope to get active customers
     */
    public function scopeActive($query)
    {
        return $query->where('last_activity_at', '>', now()->subDays(30));
    }

    /**
     * Scope to get customers by segment
     */
    public function scopeBySegment($query, $segment)
    {
        return $query->where('segment', $segment);
    }

    /**
     * Calculate and update analytics for a user
     */
    public static function updateForUser($userId)
    {
        $user = User::find($userId);
        if (!$user) return;

        $orders = $user->orders()->where('status', '!=', 'cancelled');
        $totalOrders = $orders->count();
        $totalSpent = $orders->sum('total');

        $analytics = self::updateOrCreate(
            ['user_id' => $userId],
            [
                'total_orders' => $totalOrders,
                'total_spent' => $totalSpent,
                'average_order_value' => $totalOrders > 0 ? $totalSpent / $totalOrders : 0,
                'first_order_date' => $orders->min('created_at'),
                'last_order_date' => $orders->max('created_at'),
                'total_reviews' => $user->reviews()->count(),
                'average_rating' => $user->reviews()->avg('rating') ?? 0,
                'last_activity_at' => now(),
                'segment' => self::calculateSegment($totalSpent, $totalOrders),
                'customer_lifetime_value' => $totalSpent * 1.5, // Simple CLV calculation
                'repeat_purchase_rate' => $totalOrders > 1 ? ($totalOrders - 1) / $totalOrders : 0,
            ]
        );

        return $analytics;
    }

    /**
     * Calculate customer segment based on spending and order count
     */
    protected static function calculateSegment($totalSpent, $totalOrders)
    {
        if ($totalSpent > 50000 && $totalOrders > 10) {
            return 'vip';
        } elseif ($totalSpent > 20000 || $totalOrders > 5) {
            return 'loyal';
        } elseif ($totalOrders > 1) {
            return 'returning';
        } elseif ($totalOrders == 1) {
            return 'new';
        } else {
            return 'potential';
        }
    }
}