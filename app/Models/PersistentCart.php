<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PersistentCart extends Model
{
    protected $fillable = [
        'session_id',
        'user_id',
        'cart_data',
        'total_amount',
        'items_count',
        'currency',
        'last_activity',
        'expires_at',
        'recovery_token',
        'is_abandoned',
        'abandoned_at',
        'recovery_email_count',
        'last_recovery_email_sent',
        'status', // new: new, active, abandoned, recovered, expired
        'recovery_probability', // new: ML-based recovery probability (0-100)
        'customer_segment', // new: high_value, repeat, vip, regular
        'device_type', // new: mobile, desktop, tablet
        'source', // new: direct, ad, organic, email
    ];

    protected $casts = [
        'cart_data' => 'array',
        'total_amount' => 'decimal:2',
        'items_count' => 'integer',
        'is_abandoned' => 'boolean',
        'recovery_email_count' => 'integer',
        'last_activity' => 'datetime',
        'expires_at' => 'datetime',
        'abandoned_at' => 'datetime',
        'last_recovery_email_sent' => 'datetime',
        'recovery_probability' => 'integer',
    ];

    const STATUS_NEW = 'new';
    const STATUS_ACTIVE = 'active';
    const STATUS_ABANDONED = 'abandoned';
    const STATUS_RECOVERED = 'recovered';
    const STATUS_EXPIRED = 'expired';

    const SEGMENT_HIGH_VALUE = 'high_value';
    const SEGMENT_REPEAT = 'repeat';
    const SEGMENT_VIP = 'vip';
    const SEGMENT_REGULAR = 'regular';

    /**
     * Get the user that owns the cart
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the recovery discounts for this cart
     */
    public function discounts(): HasMany
    {
        return $this->hasMany(CartRecoveryDiscount::class);
    }

    /**
     * Get the active/valid discount for this cart
     */
    public function activeDiscount()
    {
        return $this->discounts()
            ->where('is_used', false)
            ->where(function($q) {
                $q->whereNull('valid_until')
                  ->orWhere('valid_until', '>=', now());
            })
            ->first();
    }

    /**
     * Calculate recovery probability based on multiple factors
     */
    public function calculateRecoveryProbability(): int
    {
        $probability = 50; // base probability

        // Factor 1: Time since abandonment (newer is better)
        if ($this->abandoned_at) {
            $hoursAgo = now()->diffInHours($this->abandoned_at);
            if ($hoursAgo < 2) {
                $probability += 25;
            } elseif ($hoursAgo < 24) {
                $probability += 15;
            } elseif ($hoursAgo < 72) {
                $probability += 5;
            }
        }

        // Factor 2: Cart value (higher value = higher priority)
        if ($this->total_amount > 1000) {
            $probability += 20;
        } elseif ($this->total_amount > 500) {
            $probability += 10;
        }

        // Factor 3: User history
        if ($this->user) {
            $orderCount = $this->user->orders()->count();
            if ($orderCount > 5) {
                $probability += 15;
            } elseif ($orderCount > 0) {
                $probability += 8;
            }
        }

        // Factor 4: Email engagement (if previously sent)
        if ($this->recovery_email_count > 0) {
            $probability -= 10; // Reduce if already sent emails
        }

        // Factor 5: Device type (desktop recovery rate is typically higher)
        if ($this->device_type === 'desktop') {
            $probability += 8;
        } elseif ($this->device_type === 'mobile') {
            $probability -= 5;
        }

        return min(100, max(0, $probability));
    }

    /**
     * Determine customer segment
     */
    public function determineSegment(): string
    {
        if (!$this->user) {
            return self::SEGMENT_REGULAR;
        }

        $orderCount = $this->user->orders()->count();
        $totalSpent = $this->user->orders()
            ->where('status', 'completed')
            ->sum('total_amount');

        if ($totalSpent > 10000 && $orderCount > 5) {
            return self::SEGMENT_VIP;
        } elseif ($this->total_amount > 1000) {
            return self::SEGMENT_HIGH_VALUE;
        } elseif ($orderCount > 2) {
            return self::SEGMENT_REPEAT;
        }

        return self::SEGMENT_REGULAR;
    }

    /**
     * Mark cart as recovered
     */
    public function markAsRecovered(): void
    {
        $this->update([
            'is_abandoned' => false,
            'status' => self::STATUS_RECOVERED,
        ]);
    }

    /**
     * Mark cart as expired
     */
    public function markAsExpired(): void
    {
        $this->update([
            'status' => self::STATUS_EXPIRED,
        ]);
    }

    /**
     * Update cart status based on activity
     */
    public function updateStatus(): void
    {
        if ($this->status === self::STATUS_RECOVERED) {
            return;
        }

        if (now()->isAfter($this->expires_at)) {
            $this->markAsExpired();
            return;
        }

        if ($this->is_abandoned) {
            if ($this->status !== self::STATUS_ABANDONED) {
                $this->update(['status' => self::STATUS_ABANDONED]);
            }
        } else {
            $this->update(['status' => self::STATUS_ACTIVE]);
        }
    }
}
