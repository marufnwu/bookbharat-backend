<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class ProductBundleVariant extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'product_id',
        'name',
        'sku',
        'quantity',
        'pricing_type',
        'discount_percentage',
        'fixed_price',
        'fixed_discount',
        'compare_price',
        'stock_management_type',
        'stock_quantity',
        'is_active',
        'sort_order',
        'metadata',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'discount_percentage' => 'decimal:2',
        'fixed_price' => 'decimal:2',
        'fixed_discount' => 'decimal:2',
        'compare_price' => 'decimal:2',
        'stock_quantity' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'metadata' => 'array',
    ];

    protected $appends = [
        'calculated_price',
        'savings_amount',
        'savings_percentage',
        'effective_stock',
        'formatted_name',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable();
    }

    // Relationships
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class, 'bundle_variant_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'bundle_variant_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInStock($query)
    {
        return $query->where(function ($q) {
            $q->where('stock_management_type', 'use_main_product')
              ->orWhere(function ($sq) {
                  $sq->where('stock_management_type', 'separate_stock')
                     ->where('stock_quantity', '>', 0);
              });
        });
    }

    // Accessors
    public function getCalculatedPriceAttribute()
    {
        return $this->calculatePrice();
    }

    public function getSavingsAmountAttribute()
    {
        $originalPrice = $this->product->price * $this->quantity;
        $bundlePrice = $this->calculatePrice();
        return max(0, $originalPrice - $bundlePrice);
    }

    public function getSavingsPercentageAttribute()
    {
        $originalPrice = $this->product->price * $this->quantity;
        if ($originalPrice <= 0) {
            return 0;
        }
        $savings = $this->getSavingsAmountAttribute();
        return round(($savings / $originalPrice) * 100, 2);
    }

    public function getEffectiveStockAttribute()
    {
        if ($this->stock_management_type === 'separate_stock') {
            return $this->stock_quantity;
        }

        // For 'use_main_product', calculate how many bundles can be made
        if ($this->product && $this->quantity > 0) {
            return floor($this->product->stock_quantity / $this->quantity);
        }

        return 0;
    }

    public function getFormattedNameAttribute()
    {
        $savingsPercent = $this->getSavingsPercentageAttribute();
        if ($savingsPercent > 0) {
            return "{$this->name} - Save {$savingsPercent}%";
        }
        return $this->name;
    }

    // Methods
    public function calculatePrice(): float
    {
        if (!$this->product) {
            return 0;
        }

        $basePrice = $this->product->price * $this->quantity;

        switch ($this->pricing_type) {
            case 'percentage_discount':
                if ($this->discount_percentage > 0) {
                    return round($basePrice * (1 - $this->discount_percentage / 100), 2);
                }
                return $basePrice;

            case 'fixed_price':
                return $this->fixed_price ?? $basePrice;

            case 'fixed_discount':
                if ($this->fixed_discount > 0) {
                    return max(0, round($basePrice - $this->fixed_discount, 2));
                }
                return $basePrice;

            default:
                return $basePrice;
        }
    }

    public function reduceStock(int $orderQuantity): bool
    {
        if ($this->stock_management_type === 'separate_stock') {
            if ($this->stock_quantity >= $orderQuantity) {
                $this->decrement('stock_quantity', $orderQuantity);
                return true;
            }
            return false;
        }

        // For 'use_main_product', reduce the main product stock
        if ($this->product) {
            $requiredStock = $this->quantity * $orderQuantity;
            if ($this->product->stock_quantity >= $requiredStock) {
                $this->product->decrement('stock_quantity', $requiredStock);
                return true;
            }
            return false;
        }

        return false;
    }

    public function increaseStock(int $orderQuantity): void
    {
        if ($this->stock_management_type === 'separate_stock') {
            $this->increment('stock_quantity', $orderQuantity);
        } elseif ($this->product) {
            // For 'use_main_product', increase the main product stock
            $returnStock = $this->quantity * $orderQuantity;
            $this->product->increment('stock_quantity', $returnStock);
        }
    }

    public function canPurchase(int $orderQuantity = 1): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->stock_management_type === 'separate_stock') {
            return $this->stock_quantity >= $orderQuantity;
        }

        // For 'use_main_product', check if product has enough stock
        if ($this->product) {
            $requiredStock = $this->quantity * $orderQuantity;
            return $this->product->stock_quantity >= $requiredStock;
        }

        return false;
    }

    /**
     * Get the original price (before bundle discount)
     */
    public function getOriginalPrice(): float
    {
        if (!$this->product) {
            return 0;
        }
        return $this->product->price * $this->quantity;
    }

    /**
     * Check if this bundle variant is a better deal than buying individually
     */
    public function isBetterDeal(): bool
    {
        $originalPrice = $this->getOriginalPrice();
        $bundlePrice = $this->calculatePrice();
        return $bundlePrice < $originalPrice;
    }
}

