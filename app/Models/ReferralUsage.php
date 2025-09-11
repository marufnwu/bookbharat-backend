<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralUsage extends Model
{
    use HasFactory;

    protected $fillable = [
        'referral_code_id',
        'order_id',
        'user_id',
        'discount_amount',
        'commission_amount',
        'order_total',
        'metadata',
    ];

    protected $casts = [
        'discount_amount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'order_total' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function referralCode(): BelongsTo
    {
        return $this->belongsTo(ReferralCode::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getFormattedDiscountAmountAttribute(): string
    {
        return '₹' . number_format($this->discount_amount, 2);
    }

    public function getFormattedCommissionAmountAttribute(): string
    {
        return '₹' . number_format($this->commission_amount, 2);
    }

    public function getFormattedOrderTotalAttribute(): string
    {
        return '₹' . number_format($this->order_total, 2);
    }

    public function scopeByReferralCode($query, int $referralCodeId)
    {
        return $query->where('referral_code_id', $referralCodeId);
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecentUsages($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}