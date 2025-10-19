<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'cart_id',
        'product_id',
        'variant_id',
        'bundle_variant_id',
        'quantity',
        'unit_price',
        'attributes'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'attributes' => 'array',
    ];

    // Relationships
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function bundleVariant(): BelongsTo
    {
        return $this->belongsTo(ProductBundleVariant::class, 'bundle_variant_id');
    }

    // Accessors
    public function getTotalPriceAttribute()
    {
        // If this is a bundle variant, use the bundle's calculated price
        if ($this->bundle_variant_id && $this->bundleVariant) {
            return $this->bundleVariant->calculated_price * $this->quantity;
        }

        return $this->unit_price * $this->quantity;
    }

    public function getIsBundleAttribute(): bool
    {
        return $this->bundle_variant_id !== null;
    }
}
