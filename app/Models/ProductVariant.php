<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class ProductVariant extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'product_id',
        'sku',
        'price',
        'compare_price',
        'cost_price',
        'stock_quantity',
        'reserved_quantity',
        'weight',
        'dimensions',
        'barcode',
        'combination_hash',
        'is_active',
        'track_quantity',
        'image',
        'variant_attributes',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'compare_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'weight' => 'decimal:2',
        'stock_quantity' => 'integer',
        'reserved_quantity' => 'integer',
        'is_active' => 'boolean',
        'track_quantity' => 'boolean',
        'variant_attributes' => 'array',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attributes(): HasMany
    {
        return $this->hasMany(ProductVariantAttribute::class, 'variant_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'variant_id');
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class, 'variant_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInStock($query)
    {
        return $query->where('stock_quantity', '>', 0);
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_active', true)
            ->whereRaw('stock_quantity > reserved_quantity');
    }

    // Accessors
    public function getAvailableStockAttribute()
    {
        return max(0, $this->stock_quantity - $this->reserved_quantity);
    }

    public function getDiscountPercentageAttribute()
    {
        if ($this->compare_price && $this->compare_price > $this->price) {
            return round((($this->compare_price - $this->price) / $this->compare_price) * 100);
        }
        return 0;
    }

    public function getFormattedAttributesAttribute()
    {
        return $this->attributes()->with(['attribute', 'attributeValue'])->get()
            ->map(function ($attr) {
                return [
                    'attribute' => $attr->attribute->name,
                    'value' => $attr->attributeValue->value,
                    'color_code' => $attr->attributeValue->color_code,
                    'image' => $attr->attributeValue->image,
                ];
            });
    }

    // Methods
    public function reserveStock($quantity)
    {
        if ($this->available_stock >= $quantity) {
            $this->increment('reserved_quantity', $quantity);
            return true;
        }
        return false;
    }

    public function releaseStock($quantity)
    {
        $this->decrement('reserved_quantity', min($quantity, $this->reserved_quantity));
    }

    public function adjustStock($quantity)
    {
        $this->increment('stock_quantity', $quantity);
    }

    public function generateCombinationHash(array $attributeValues)
    {
        sort($attributeValues);
        return md5(implode('-', $attributeValues));
    }
}