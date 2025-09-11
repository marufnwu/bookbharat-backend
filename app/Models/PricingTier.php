<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PricingTier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'discount_percentage',
        'flat_discount',
        'minimum_quantity',
        'maximum_quantity',
        'is_active'
    ];

    protected $casts = [
        'discount_percentage' => 'decimal:2',
        'flat_discount' => 'decimal:2',
        'minimum_quantity' => 'integer',
        'maximum_quantity' => 'integer',
        'is_active' => 'boolean'
    ];

    // Relationships
    public function customerGroups(): HasMany
    {
        return $this->hasMany(CustomerGroup::class);
    }

    public function pricingRules(): HasMany
    {
        return $this->hasMany(PricingRule::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Methods
    public function calculateDiscount($originalPrice, $quantity = 1)
    {
        if (!$this->is_active) {
            return 0;
        }

        if ($this->minimum_quantity && $quantity < $this->minimum_quantity) {
            return 0;
        }

        if ($this->maximum_quantity && $quantity > $this->maximum_quantity) {
            return 0;
        }

        if ($this->discount_percentage) {
            return $originalPrice * ($this->discount_percentage / 100);
        }

        if ($this->flat_discount) {
            return min($this->flat_discount, $originalPrice);
        }

        return 0;
    }

    public function calculateFinalPrice($originalPrice, $quantity = 1)
    {
        $discount = $this->calculateDiscount($originalPrice, $quantity);
        return max(0, $originalPrice - $discount);
    }
}