<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Wishlist extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_id',
        'product_variant_id',
        'notes',
        'priority'
    ];

    protected $casts = [
        'priority' => 'integer',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    // Scopes
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeHighPriority($query)
    {
        return $query->where('priority', '>=', 4);
    }

    // Accessors
    public function getPriorityLabelAttribute()
    {
        return match($this->priority) {
            5 => 'Very High',
            4 => 'High',
            3 => 'Medium',
            2 => 'Low',
            1 => 'Very Low',
            default => 'Medium'
        };
    }

    // Helper methods
    public function getProductDetails()
    {
        return [
            'id' => $this->product->id,
            'name' => $this->product->name,
            'slug' => $this->product->slug,
            'price' => $this->productVariant ? $this->productVariant->price : $this->product->price,
            'original_price' => $this->productVariant ? $this->productVariant->original_price : $this->product->original_price,
            'image' => $this->product->featured_image,
            'is_available' => $this->product->is_active && $this->product->stock_quantity > 0,
            'variant' => $this->productVariant ? [
                'id' => $this->productVariant->id,
                'sku' => $this->productVariant->sku,
                'attributes' => $this->productVariant->attributes
            ] : null
        ];
    }
}