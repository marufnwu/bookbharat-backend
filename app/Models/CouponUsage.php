<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponUsage extends Model
{
    use HasFactory;

    protected $fillable = [
        'coupon_id',
        'user_id',
        'order_id',
        'discount_amount',
        'order_total_before_discount',
        'order_total_after_discount',
        'applied_products',
        'usage_context',
    ];

    protected $casts = [
        'discount_amount' => 'decimal:2',
        'order_total_before_discount' => 'decimal:2',
        'order_total_after_discount' => 'decimal:2',
        'applied_products' => 'array',
        'usage_context' => 'array',
    ];

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function getFormattedDiscountAmountAttribute(): string
    {
        return '₹' . number_format($this->discount_amount, 2);
    }

    public function getDiscountPercentageAttribute(): float
    {
        if ($this->order_total_before_discount <= 0) {
            return 0;
        }

        return ($this->discount_amount / $this->order_total_before_discount) * 100;
    }

    public function getSavingsAttribute(): array
    {
        return [
            'amount' => $this->discount_amount,
            'percentage' => $this->discount_percentage,
            'formatted_amount' => $this->formatted_discount_amount,
        ];
    }

    // Scopes
    public function scopeByCoupon($query, int $couponId)
    {
        return $query->where('coupon_id', $couponId);
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereBetween('created_at', [
            now()->startOfMonth(),
            now()->endOfMonth()
        ]);
    }

    public function scopeLastMonth($query)
    {
        return $query->whereBetween('created_at', [
            now()->subMonth()->startOfMonth(),
            now()->subMonth()->endOfMonth()
        ]);
    }

    public function scopeHighValue($query, float $threshold = 100)
    {
        return $query->where('discount_amount', '>=', $threshold);
    }
}