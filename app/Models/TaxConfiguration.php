<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TaxConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'tax_type',
        'rate',
        'is_enabled',
        'apply_on',
        'conditions',
        'is_inclusive',
        'priority',
        'description',
        'display_label',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'is_inclusive' => 'boolean',
        'rate' => 'decimal:2',
        'conditions' => 'array',
        'priority' => 'integer',
    ];

    /**
     * Get all enabled taxes applicable for the given context
     */
    public static function getApplicableTaxes($orderContext = [])
    {
        return static::where('is_enabled', true)
            ->orderBy('priority', 'asc')
            ->get()
            ->filter(function ($tax) use ($orderContext) {
                return $tax->isApplicable($orderContext);
            });
    }

    /**
     * Check if this tax is applicable for the given context
     */
    public function isApplicable($orderContext = [])
    {
        if (!$this->conditions) {
            return true;
        }

        $conditions = $this->conditions;

        // State-based tax
        if (isset($conditions['state_based']) && $conditions['state_based']) {
            $allowedStates = $conditions['states'] ?? [];
            if (!empty($allowedStates) && !in_array($orderContext['state'] ?? null, $allowedStates)) {
                return false;
            }
        }

        // Category-based tax
        if (isset($conditions['product_categories']) && !empty($conditions['product_categories'])) {
            $orderCategories = $orderContext['categories'] ?? [];
            if (!array_intersect($orderCategories, $conditions['product_categories'])) {
                return false;
            }
        }

        // Minimum order value
        if (isset($conditions['min_order_value']) && ($orderContext['order_value'] ?? 0) < $conditions['min_order_value']) {
            return false;
        }

        return true;
    }

    /**
     * Calculate tax amount
     */
    public function calculateTax($taxableAmount)
    {
        if ($this->is_inclusive) {
            // Tax is already included in price
            // Calculate tax component: tax = amount - (amount / (1 + rate/100))
            return $taxableAmount - ($taxableAmount / (1 + ($this->rate / 100)));
        } else {
            // Tax is added on top
            return ($taxableAmount * $this->rate) / 100;
        }
    }
}
