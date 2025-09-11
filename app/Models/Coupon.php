<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'value',
        'minimum_order_amount',
        'maximum_discount_amount',
        'usage_limit',
        'usage_limit_per_customer',
        'usage_count',
        'starts_at',
        'expires_at',
        'is_active',
        'is_stackable',
        'applicable_products',
        'applicable_categories',
        'applicable_customer_groups',
        'excluded_products',
        'excluded_categories',
        'first_order_only',
        'buy_x_get_y_config',
        'geographic_restrictions',
        'day_time_restrictions',
        'created_by',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'minimum_order_amount' => 'decimal:2',
        'maximum_discount_amount' => 'decimal:2',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'is_stackable' => 'boolean',
        'applicable_products' => 'array',
        'applicable_categories' => 'array',
        'applicable_customer_groups' => 'array',
        'excluded_products' => 'array',
        'excluded_categories' => 'array',
        'buy_x_get_y_config' => 'array',
        'geographic_restrictions' => 'array',
        'day_time_restrictions' => 'array',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function getFormattedValueAttribute(): string
    {
        return match($this->type) {
            'percentage' => $this->value . '%',
            'fixed_amount' => '₹' . number_format($this->value, 2),
            'free_shipping' => 'Free Shipping',
            'buy_x_get_y' => 'Buy ' . ($this->buy_x_get_y_config['buy_quantity'] ?? 'X') . ' Get ' . ($this->buy_x_get_y_config['get_quantity'] ?? 'Y'),
            default => 'Special Offer'
        };
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

        if ($this->starts_at->isFuture()) {
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

    public function getRemainingUsesAttribute(): ?int
    {
        if (!$this->usage_limit) {
            return null;
        }

        return max(0, $this->usage_limit - $this->usage_count);
    }

    public function getUsagePercentageAttribute(): float
    {
        if (!$this->usage_limit) {
            return 0;
        }

        return ($this->usage_count / $this->usage_limit) * 100;
    }

    public function getTotalDiscountGivenAttribute(): float
    {
        return $this->usages()->sum('discount_amount');
    }

    public function getAverageDiscountAttribute(): float
    {
        if ($this->usage_count === 0) {
            return 0;
        }

        return $this->total_discount_given / $this->usage_count;
    }

    public function canBeUsedBy(User $user): bool
    {
        if (!$this->is_valid) {
            return false;
        }

        // Check usage limit per customer
        if ($this->usage_limit_per_customer) {
            $userUsageCount = $this->usages()->where('user_id', $user->id)->count();
            if ($userUsageCount >= $this->usage_limit_per_customer) {
                return false;
            }
        }

        // Check first order only
        if ($this->first_order_only === 'yes') {
            $hasOrders = $user->orders()->where('status', '!=', 'cancelled')->exists();
            if ($hasOrders) {
                return false;
            }
        }

        // Check customer group restrictions
        if (!empty($this->applicable_customer_groups)) {
            $userGroupIds = $user->customerGroups()->pluck('customer_groups.id')->toArray();
            if (empty(array_intersect($userGroupIds, $this->applicable_customer_groups))) {
                return false;
            }
        }

        // Check day/time restrictions
        if (!empty($this->day_time_restrictions)) {
            if (!$this->isWithinTimeRestrictions()) {
                return false;
            }
        }

        return true;
    }

    public function isApplicableToProduct(Product $product): bool
    {
        // Check excluded products
        if (!empty($this->excluded_products) && in_array($product->id, $this->excluded_products)) {
            return false;
        }

        // Check excluded categories
        if (!empty($this->excluded_categories) && in_array($product->category_id, $this->excluded_categories)) {
            return false;
        }

        // Check specific products (if specified, only these products are eligible)
        if (!empty($this->applicable_products)) {
            return in_array($product->id, $this->applicable_products);
        }

        // Check specific categories (if specified, only these categories are eligible)
        if (!empty($this->applicable_categories)) {
            return in_array($product->category_id, $this->applicable_categories);
        }

        return true;
    }

    public function calculateDiscount(float $orderTotal, array $cartItems = []): array
    {
        $discount = 0;
        $freeShipping = false;
        $applicableItems = [];

        switch ($this->type) {
            case 'percentage':
                $discount = ($orderTotal * $this->value) / 100;
                if ($this->maximum_discount_amount) {
                    $discount = min($discount, $this->maximum_discount_amount);
                }
                break;

            case 'fixed_amount':
                $discount = min($this->value, $orderTotal);
                break;

            case 'free_shipping':
                $freeShipping = true;
                break;

            case 'buy_x_get_y':
                $result = $this->calculateBuyXGetYDiscount($cartItems);
                $discount = $result['discount'];
                $applicableItems = $result['applicable_items'];
                break;
        }

        return [
            'discount_amount' => $discount,
            'free_shipping' => $freeShipping,
            'applicable_items' => $applicableItems,
            'coupon_type' => $this->type,
        ];
    }

    protected function calculateBuyXGetYDiscount(array $cartItems): array
    {
        $config = $this->buy_x_get_y_config;
        $buyQuantity = $config['buy_quantity'] ?? 1;
        $getQuantity = $config['get_quantity'] ?? 1;
        $specificProductId = $config['product_id'] ?? null;

        $eligibleItems = collect($cartItems)->filter(function ($item) use ($specificProductId) {
            if ($specificProductId) {
                return $item['product_id'] == $specificProductId;
            }
            return $this->isApplicableToProduct($item['product']);
        });

        $totalEligibleQuantity = $eligibleItems->sum('quantity');
        $freeQuantity = intval($totalEligibleQuantity / $buyQuantity) * $getQuantity;
        
        if ($freeQuantity > 0) {
            // Calculate discount based on cheapest items
            $sortedItems = $eligibleItems->sortBy('price');
            $discountAmount = 0;
            $remainingFreeQuantity = $freeQuantity;

            foreach ($sortedItems as $item) {
                if ($remainingFreeQuantity <= 0) break;
                
                $itemFreeQuantity = min($remainingFreeQuantity, $item['quantity']);
                $discountAmount += $itemFreeQuantity * $item['price'];
                $remainingFreeQuantity -= $itemFreeQuantity;
            }

            return [
                'discount' => $discountAmount,
                'applicable_items' => $eligibleItems->toArray()
            ];
        }

        return ['discount' => 0, 'applicable_items' => []];
    }

    protected function isWithinTimeRestrictions(): bool
    {
        $restrictions = $this->day_time_restrictions;
        $now = now();

        // Check allowed days
        if (!empty($restrictions['allowed_days'])) {
            $currentDay = strtolower($now->format('l')); // monday, tuesday, etc.
            if (!in_array($currentDay, $restrictions['allowed_days'])) {
                return false;
            }
        }

        // Check allowed hours
        if (!empty($restrictions['allowed_hours'])) {
            $currentHour = (int) $now->format('H');
            $startHour = $restrictions['allowed_hours']['start'] ?? 0;
            $endHour = $restrictions['allowed_hours']['end'] ?? 23;
            
            if ($currentHour < $startHour || $currentHour > $endHour) {
                return false;
            }
        }

        return true;
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeValid($query)
    {
        return $query->where('is_active', true)
                    ->where('starts_at', '<=', now())
                    ->where(function ($q) {
                        $q->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                    })
                    ->where(function ($q) {
                        $q->whereNull('usage_limit')
                          ->orWhereColumn('usage_count', '<', 'usage_limit');
                    });
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeForCustomerGroup($query, array $groupIds)
    {
        return $query->where(function ($q) use ($groupIds) {
            $q->whereNull('applicable_customer_groups')
              ->orWhere(function ($subQ) use ($groupIds) {
                  foreach ($groupIds as $groupId) {
                      $subQ->orWhereJsonContains('applicable_customer_groups', $groupId);
                  }
              });
        });
    }

    public function scopeExpiringSoon($query, int $days = 7)
    {
        return $query->where('expires_at', '>', now())
                    ->where('expires_at', '<=', now()->addDays($days));
    }
}