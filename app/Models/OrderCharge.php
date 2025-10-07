<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderCharge extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'type',
        'amount',
        'percentage',
        'tiers',
        'is_enabled',
        'apply_to',
        'payment_methods',
        'conditions',
        'priority',
        'description',
        'display_label',
        'is_taxable',
        'apply_after_discount',
        'is_refundable',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'is_taxable' => 'boolean',
        'apply_after_discount' => 'boolean',
        'is_refundable' => 'boolean',
        'amount' => 'decimal:2',
        'percentage' => 'decimal:2',
        'tiers' => 'array',
        'payment_methods' => 'array',
        'conditions' => 'array',
        'priority' => 'integer',
    ];

    /**
     * Get all enabled charges applicable for the given context
     */
    public static function getApplicableCharges($paymentMethod = null, $orderContext = [])
    {
        return static::where('is_enabled', true)
            ->orderBy('priority', 'asc')
            ->get()
            ->filter(function ($charge) use ($paymentMethod, $orderContext) {
                return $charge->isApplicable($paymentMethod, $orderContext);
            });
    }

    /**
     * Check if this charge is applicable for the given payment method and context
     */
    public function isApplicable($paymentMethod, $orderContext = [])
    {
        // Check payment method applicability
        if ($this->apply_to === 'cod_only' && $paymentMethod !== 'cod') {
            return false;
        }

        if ($this->apply_to === 'online_only' && $paymentMethod === 'cod') {
            return false;
        }

        if ($this->apply_to === 'specific_payment_methods') {
            if (!in_array($paymentMethod, $this->payment_methods ?? [])) {
                return false;
            }
        }

        // Check conditions (applies to all charge types, not just 'conditional')
        if ($this->conditions && !empty($this->conditions)) {
            return $this->checkConditions($orderContext);
        }

        return true;
    }

    /**
     * Check if conditions are met
     */
    protected function checkConditions($orderContext)
    {
        $conditions = $this->conditions;

        // Minimum order value
        if (isset($conditions['min_order_value']) && ($orderContext['order_value'] ?? 0) < $conditions['min_order_value']) {
            return false;
        }

        // Maximum order value
        if (isset($conditions['max_order_value']) && ($orderContext['order_value'] ?? 0) > $conditions['max_order_value']) {
            return false;
        }

        // Exempt above certain value
        if (isset($conditions['exempt_above_value']) && ($orderContext['order_value'] ?? 0) >= $conditions['exempt_above_value']) {
            return false;
        }

        // Category exclusions
        if (isset($conditions['excluded_categories']) && !empty($conditions['excluded_categories'])) {
            $orderCategories = $orderContext['categories'] ?? [];
            if (array_intersect($orderCategories, $conditions['excluded_categories'])) {
                return false;
            }
        }

        // Pincode exclusions
        if (isset($conditions['excluded_pincodes']) && in_array($orderContext['pincode'] ?? null, $conditions['excluded_pincodes'])) {
            return false;
        }

        // State inclusions
        if (isset($conditions['included_states']) && !empty($conditions['included_states'])) {
            if (!in_array($orderContext['state'] ?? null, $conditions['included_states'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Calculate the charge amount for given order value
     */
    public function calculateCharge($orderValue)
    {
        switch ($this->type) {
            case 'fixed':
                return $this->amount;

            case 'percentage':
                return ($orderValue * $this->percentage) / 100;

            case 'tiered':
                return $this->calculateTieredCharge($orderValue);

            default:
                return 0;
        }
    }

    /**
     * Calculate tiered charge based on order value
     */
    protected function calculateTieredCharge($orderValue)
    {
        if (!$this->tiers) {
            return 0;
        }

        foreach ($this->tiers as $tier) {
            $min = $tier['min'] ?? 0;
            $max = $tier['max'] ?? PHP_FLOAT_MAX;

            if ($orderValue >= $min && $orderValue <= $max) {
                $charge = $tier['charge'];
                
                // Check if it's a percentage (string ending with %)
                if (is_string($charge) && str_ends_with($charge, '%')) {
                    $percentage = floatval(str_replace('%', '', $charge));
                    return ($orderValue * $percentage) / 100;
                }
                
                return floatval($charge);
            }
        }

        return 0;
    }

    /**
     * Get advance payment configuration for COD charges
     */
    public function getAdvancePaymentConfig()
    {
        if ($this->apply_to !== 'cod_only') {
            return null;
        }

        $conditions = $this->conditions ?? [];

        if (!isset($conditions['advance_payment'])) {
            return null;
        }

        return $conditions['advance_payment'];
    }

    /**
     * Check if advance payment is required
     */
    public function requiresAdvancePayment()
    {
        $config = $this->getAdvancePaymentConfig();
        return $config && ($config['required'] ?? false);
    }

    /**
     * Calculate advance payment amount
     */
    public function calculateAdvancePayment($orderTotal)
    {
        $config = $this->getAdvancePaymentConfig();

        if (!$config || !($config['required'] ?? false)) {
            return 0;
        }

        $type = $config['type'] ?? 'percentage';
        $value = $config['value'] ?? 0;

        if ($type === 'percentage') {
            return round(($orderTotal * $value) / 100, 2);
        } elseif ($type === 'fixed') {
            return round($value, 2);
        }

        return 0;
    }
}
