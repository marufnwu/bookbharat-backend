<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ShippingInsurance extends Model
{
    use HasFactory;

    protected $table = 'shipping_insurance';

    protected $fillable = [
        'name',
        'description',
        'min_order_value',
        'max_order_value',
        'coverage_percentage',
        'premium_percentage',
        'minimum_premium',
        'maximum_premium',
        'is_mandatory',
        'conditions',
        'is_active',
        'claim_processing_days',
    ];

    protected $casts = [
        'min_order_value' => 'decimal:2',
        'max_order_value' => 'decimal:2',
        'coverage_percentage' => 'decimal:2',
        'premium_percentage' => 'decimal:2',
        'minimum_premium' => 'decimal:2',
        'maximum_premium' => 'decimal:2',
        'is_mandatory' => 'boolean',
        'is_active' => 'boolean',
        'conditions' => 'array',
        'claim_processing_days' => 'integer',
    ];

    public function scopeActive(Builder $query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForOrderValue(Builder $query, float $orderValue)
    {
        return $query->where('min_order_value', '<=', $orderValue)
                    ->where(function ($q) use ($orderValue) {
                        $q->whereNull('max_order_value')
                          ->orWhere('max_order_value', '>=', $orderValue);
                    });
    }

    public function scopeMandatory(Builder $query)
    {
        return $query->where('is_mandatory', true);
    }

    /**
     * Calculate insurance premium for given order value
     */
    public function calculatePremium(float $orderValue, array $options = []): array
    {
        // Check if order value is within coverage range
        if ($orderValue < $this->min_order_value || 
            ($this->max_order_value && $orderValue > $this->max_order_value)) {
            return [
                'eligible' => false,
                'premium' => 0,
                'coverage_amount' => 0,
                'reason' => 'Order value outside coverage range'
            ];
        }

        // Calculate base premium
        $premium = ($orderValue * $this->premium_percentage / 100);

        // Apply minimum premium
        if ($premium < $this->minimum_premium) {
            $premium = $this->minimum_premium;
        }

        // Apply maximum premium if set
        if ($this->maximum_premium && $premium > $this->maximum_premium) {
            $premium = $this->maximum_premium;
        }

        // Apply conditions
        if ($this->conditions) {
            $premium = $this->applyConditions($premium, $orderValue, $options);
        }

        // Calculate coverage amount
        $coverageAmount = min(
            $orderValue * ($this->coverage_percentage / 100),
            $this->max_order_value ?? $orderValue
        );

        return [
            'eligible' => true,
            'premium' => round($premium, 2),
            'coverage_amount' => round($coverageAmount, 2),
            'coverage_percentage' => $this->coverage_percentage,
            'plan_name' => $this->name,
            'claim_processing_days' => $this->claim_processing_days,
        ];
    }

    /**
     * Apply special conditions to premium calculation
     */
    protected function applyConditions(float $premium, float $orderValue, array $options = []): float
    {
        foreach ($this->conditions as $condition) {
            switch ($condition['type'] ?? null) {
                case 'zone_multiplier':
                    $zone = $options['zone'] ?? null;
                    if ($zone && isset($condition['zones'][$zone])) {
                        $premium *= $condition['zones'][$zone];
                    }
                    break;

                case 'remote_surcharge':
                    if ($options['is_remote'] ?? false) {
                        $premium += $condition['amount'] ?? 0;
                    }
                    break;

                case 'high_value_discount':
                    if ($orderValue >= ($condition['threshold'] ?? 10000)) {
                        $premium *= (1 - ($condition['discount_percent'] ?? 10) / 100);
                    }
                    break;

                case 'fragile_item_surcharge':
                    if ($options['has_fragile_items'] ?? false) {
                        $premium *= ($condition['multiplier'] ?? 1.5);
                    }
                    break;

                case 'electronics_surcharge':
                    if ($options['has_electronics'] ?? false) {
                        $premium *= ($condition['multiplier'] ?? 1.3);
                    }
                    break;
            }
        }

        return $premium;
    }

    /**
     * Check if insurance is mandatory for given conditions
     */
    public static function isMandatoryForConditions(float $orderValue, array $options = []): bool
    {
        $mandatoryInsurance = static::active()
            ->mandatory()
            ->forOrderValue($orderValue)
            ->first();

        if (!$mandatoryInsurance) {
            return false;
        }

        // Check specific conditions that might make insurance mandatory
        if ($mandatoryInsurance->conditions) {
            foreach ($mandatoryInsurance->conditions as $condition) {
                switch ($condition['type'] ?? null) {
                    case 'high_value_mandatory':
                        if ($orderValue >= ($condition['threshold'] ?? 5000)) {
                            return true;
                        }
                        break;

                    case 'remote_area_mandatory':
                        if ($options['is_remote'] ?? false) {
                            return true;
                        }
                        break;

                    case 'fragile_mandatory':
                        if ($options['has_fragile_items'] ?? false) {
                            return true;
                        }
                        break;
                }
            }
        }

        return $mandatoryInsurance->is_mandatory;
    }

    /**
     * Get available insurance options for order
     */
    public static function getAvailableOptionsForOrder(float $orderValue, array $options = []): array
    {
        $insuranceOptions = static::active()
            ->forOrderValue($orderValue)
            ->orderBy('premium_percentage')
            ->get();

        $availableOptions = [];

        foreach ($insuranceOptions as $insurance) {
            $calculation = $insurance->calculatePremium($orderValue, $options);
            
            if ($calculation['eligible']) {
                $availableOptions[] = array_merge($calculation, [
                    'id' => $insurance->id,
                    'name' => $insurance->name,
                    'description' => $insurance->description,
                    'is_mandatory' => $insurance->is_mandatory,
                ]);
            }
        }

        return $availableOptions;
    }

    protected static function booted()
    {
        static::saved(function ($insurance) {
            cache()->forget('shipping_insurance_options');
        });

        static::deleted(function ($insurance) {
            cache()->forget('shipping_insurance_options');
        });
    }
}