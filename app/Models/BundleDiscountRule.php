<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class BundleDiscountRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'min_products',
        'max_products',
        'discount_percentage',
        'fixed_discount',
        'discount_type',
        'category_id',
        'customer_tier',
        'is_active',
        'priority',
        'valid_from',
        'valid_until',
        'conditions',
        'description',
    ];

    protected $casts = [
        'min_products' => 'integer',
        'max_products' => 'integer',
        'discount_percentage' => 'decimal:2',
        'fixed_discount' => 'decimal:2',
        'is_active' => 'boolean',
        'priority' => 'integer',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'conditions' => 'array',
    ];

    /**
     * Get the category this rule applies to
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Scope for active rules
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for currently valid rules (within date range)
     */
    public function scopeCurrentlyValid(Builder $query): Builder
    {
        $now = Carbon::now();
        
        return $query->where(function ($q) use ($now) {
            $q->whereNull('valid_from')
              ->orWhere('valid_from', '<=', $now);
        })->where(function ($q) use ($now) {
            $q->whereNull('valid_until')
              ->orWhere('valid_until', '>=', $now);
        });
    }

    /**
     * Scope for rules applicable to a product count
     */
    public function scopeForProductCount(Builder $query, int $count): Builder
    {
        return $query->where('min_products', '<=', $count)
                     ->where(function ($q) use ($count) {
                         $q->whereNull('max_products')
                           ->orWhere('max_products', '>=', $count);
                     });
    }

    /**
     * Scope for rules applicable to a category
     */
    public function scopeForCategory(Builder $query, $categoryId = null): Builder
    {
        if (!$categoryId) {
            return $query->whereNull('category_id');
        }
        
        return $query->where(function ($q) use ($categoryId) {
            $q->whereNull('category_id')
              ->orWhere('category_id', $categoryId);
        });
    }

    /**
     * Scope for rules applicable to a customer tier
     */
    public function scopeForCustomerTier(Builder $query, $tier = null): Builder
    {
        if (!$tier) {
            return $query->whereNull('customer_tier');
        }
        
        return $query->where(function ($q) use ($tier) {
            $q->whereNull('customer_tier')
              ->orWhere('customer_tier', $tier);
        });
    }

    /**
     * Get the applicable discount rule for given parameters
     */
    public static function getApplicableRule($productCount, $categoryId = null, $customerTier = null)
    {
        return self::active()
            ->currentlyValid()
            ->forProductCount($productCount)
            ->forCategory($categoryId)
            ->forCustomerTier($customerTier)
            ->orderBy('priority', 'desc')
            ->orderBy('discount_percentage', 'desc')
            ->first();
    }

    /**
     * Calculate discount amount based on rule type
     */
    public function calculateDiscount($totalAmount)
    {
        if ($this->discount_type === 'percentage') {
            return $totalAmount * ($this->discount_percentage / 100);
        }
        
        // Fixed discount
        return min($this->fixed_discount, $totalAmount); // Don't exceed total amount
    }

    /**
     * Check if rule matches specific conditions
     */
    public function matchesConditions(array $products): bool
    {
        if (!$this->conditions || empty($this->conditions)) {
            return true;
        }

        // Check brand conditions
        if (isset($this->conditions['brands']) && !empty($this->conditions['brands'])) {
            $productBrands = collect($products)->pluck('brand')->filter()->unique()->toArray();
            $requiredBrands = $this->conditions['brands'];
            
            if (!array_intersect($productBrands, $requiredBrands)) {
                return false;
            }
        }

        // Check minimum total amount condition
        if (isset($this->conditions['min_total']) && $this->conditions['min_total'] > 0) {
            $total = collect($products)->sum('price');
            if ($total < $this->conditions['min_total']) {
                return false;
            }
        }

        // Check for specific product IDs
        if (isset($this->conditions['product_ids']) && !empty($this->conditions['product_ids'])) {
            $productIds = collect($products)->pluck('id')->toArray();
            $requiredProductIds = $this->conditions['product_ids'];
            
            if (!array_intersect($productIds, $requiredProductIds)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get formatted discount display
     */
    public function getFormattedDiscount(): string
    {
        if ($this->discount_type === 'percentage') {
            return $this->discount_percentage . '%';
        }
        
        return '₹' . number_format($this->fixed_discount, 2);
    }

    /**
     * Check if rule is currently valid
     */
    public function isCurrentlyValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = Carbon::now();

        if ($this->valid_from && $now->lt($this->valid_from)) {
            return false;
        }

        if ($this->valid_until && $now->gt($this->valid_until)) {
            return false;
        }

        return true;
    }
}