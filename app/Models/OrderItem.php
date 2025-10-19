<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'product_sku',
        'bundle_variant_id',
        'bundle_quantity',
        'bundle_variant_name',
        'unit_price',
        'quantity',
        'total_price',
        'product_attributes'
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'quantity' => 'integer',
        'bundle_quantity' => 'integer',
        'product_attributes' => 'array',
    ];

    protected $appends = ['is_bundle', 'display_name'];

    // Relationships
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function bundleVariant(): BelongsTo
    {
        return $this->belongsTo(ProductBundleVariant::class, 'bundle_variant_id');
    }

    // Accessors
    public function getIsBundleAttribute(): bool
    {
        return $this->bundle_variant_id !== null;
    }

    public function getDisplayNameAttribute(): string
    {
        if ($this->is_bundle && $this->bundle_variant_name) {
            return "{$this->product_name} - {$this->bundle_variant_name}";
        }
        return $this->product_name;
    }

    public function getBundleDetailsAttribute(): ?array
    {
        if (!$this->is_bundle) {
            return null;
        }

        return [
            'bundle_name' => $this->bundle_variant_name,
            'items_per_bundle' => $this->bundle_quantity,
            'total_items' => $this->bundle_quantity * $this->quantity,
        ];
    }
}
