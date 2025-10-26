<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartRecoveryDiscount extends Model
{
    protected $fillable = [
        'persistent_cart_id',
        'code',
        'type', // 'percentage' or 'fixed'
        'value', // discount amount or percentage
        'min_purchase_amount',
        'max_discount_amount',
        'valid_until',
        'used_at',
        'is_used',
        'usage_count',
        'max_usage_count',
        'revenue_generated', // Track revenue from this discount
        'order_id', // If used, track which order
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'min_purchase_amount' => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'revenue_generated' => 'decimal:2',
        'valid_until' => 'datetime',
        'used_at' => 'datetime',
        'is_used' => 'boolean',
        'usage_count' => 'integer',
        'max_usage_count' => 'integer',
    ];

    /**
     * Get the persistent cart that owns this discount
     */
    public function persistentCart(): BelongsTo
    {
        return $this->belongsTo(PersistentCart::class);
    }

    /**
     * Get the order where this discount was used
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Generate a unique discount code
     */
    public static function generateCode($prefix = 'CART'): string
    {
        do {
            $code = $prefix . strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8));
        } while (self::where('code', $code)->exists());

        return $code;
    }

    /**
     * Check if discount is valid
     */
    public function isValid(): bool
    {
        if ($this->is_used && $this->usage_count >= $this->max_usage_count) {
            return false;
        }

        if ($this->valid_until && now()->greaterThan($this->valid_until)) {
            return false;
        }

        return true;
    }

    /**
     * Calculate discount amount for given cart total
     */
    public function calculateDiscount($cartTotal): float
    {
        if (!$this->isValid()) {
            return 0;
        }

        if ($this->min_purchase_amount && $cartTotal < $this->min_purchase_amount) {
            return 0;
        }

        $discount = 0;

        if ($this->type === 'percentage') {
            $discount = ($cartTotal * $this->value) / 100;
        } elseif ($this->type === 'fixed') {
            $discount = $this->value;
        }

        // Apply max discount limit
        if ($this->max_discount_amount && $discount > $this->max_discount_amount) {
            $discount = $this->max_discount_amount;
        }

        // Don't exceed cart total
        if ($discount > $cartTotal) {
            $discount = $cartTotal;
        }

        return round($discount, 2);
    }
}
