<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'session_id',
        'status',
        'currency',
        'subtotal',
        'tax_amount',
        'shipping_cost',
        'total',
        'total_items',
        'coupon_code',
        'coupon_discount',
        'coupon_free_shipping'
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }
    
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    // Accessors
    public function getTotalAmountAttribute()
    {
        return $this->cartItems->sum(function ($item) {
            return $item->unit_price * $item->quantity;
        });
    }

    public function getTotalItemsAttribute()
    {
        return $this->cartItems->sum('quantity');
    }
}
