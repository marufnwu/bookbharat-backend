<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReferralCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'code',
        'discount_type',
        'discount_value',
        'usage_limit',
        'usage_count',
        'min_order_amount',
        'expires_at',
        'is_active',
        'commission_rate',
        'commission_earned',
        'total_revenue',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'usage_limit' => 'integer',
        'usage_count' => 'integer',
        'min_order_amount' => 'decimal:2',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'commission_rate' => 'decimal:2',
        'commission_earned' => 'decimal:2',
        'total_revenue' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(ReferralUsage::class);
    }

    public function getFormattedDiscountValueAttribute(): string
    {
        if ($this->discount_type === 'percentage') {
            return $this->discount_value . '%';
        }

        return '₹' . number_format($this->discount_value, 2);
    }

    public function getFormattedMinOrderAmountAttribute(): string
    {
        return '₹' . number_format($this->min_order_amount, 2);
    }

    public function getFormattedCommissionEarnedAttribute(): string
    {
        return '₹' . number_format($this->commission_earned, 2);
    }

    public function getFormattedTotalRevenueAttribute(): string
    {
        return '₹' . number_format($this->total_revenue, 2);
    }

    public function getRemainingUsesAttribute(): ?int
    {
        if (!$this->usage_limit) {
            return null;
        }

        return max(0, $this->usage_limit - $this->usage_count);
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function getIsValidAttribute(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->is_expired) {
            return false;
        }

        if ($this->usage_limit && $this->usage_count >= $this->usage_limit) {
            return false;
        }

        return true;
    }

    public function getConversionRateAttribute(): float
    {
        if ($this->usage_count === 0) {
            return 0;
        }

        $successfulOrders = $this->orders()->where('status', '!=', 'cancelled')->count();
        
        return ($successfulOrders / $this->usage_count) * 100;
    }

    public function getAverageOrderValueAttribute(): float
    {
        if ($this->usage_count === 0) {
            return 0;
        }

        return $this->total_revenue / $this->usage_count;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeValid($query)
    {
        return $query->where('is_active', true)
                    ->where(function ($q) {
                        $q->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                    })
                    ->where(function ($q) {
                        $q->whereNull('usage_limit')
                          ->orWhereColumn('usage_count', '<', 'usage_limit');
                    });
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    public function scopeByDiscountType($query, string $type)
    {
        return $query->where('discount_type', $type);
    }

    public function scopeHighPerforming($query, float $minCommission = 1000)
    {
        return $query->where('commission_earned', '>=', $minCommission);
    }
}