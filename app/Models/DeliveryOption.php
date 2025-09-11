<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class DeliveryOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'delivery_days_min',
        'delivery_days_max',
        'price_multiplier',
        'fixed_surcharge',
        'availability_zones',
        'availability_conditions',
        'cutoff_time',
        'restricted_days',
        'min_order_value',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'delivery_days_min' => 'integer',
        'delivery_days_max' => 'integer',
        'price_multiplier' => 'decimal:2',
        'fixed_surcharge' => 'decimal:2',
        'availability_zones' => 'array',
        'availability_conditions' => 'array',
        'cutoff_time' => 'datetime:H:i:s',
        'restricted_days' => 'array',
        'min_order_value' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive(Builder $query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForZone(Builder $query, string $zone)
    {
        return $query->where(function ($q) use ($zone) {
            $q->whereJsonContains('availability_zones', $zone)
              ->orWhereNull('availability_zones')
              ->orWhereJsonLength('availability_zones', 0);
        });
    }

    public function scopeForOrderValue(Builder $query, float $orderValue)
    {
        return $query->where('min_order_value', '<=', $orderValue);
    }

    public function scopeOrderedByPriority(Builder $query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Check if delivery option is available for given conditions
     */
    public function isAvailable(string $zone, float $orderValue, array $options = []): bool
    {
        // Check if active
        if (!$this->is_active) {
            return false;
        }

        // Check zone availability
        if ($this->availability_zones && !in_array($zone, $this->availability_zones)) {
            return false;
        }

        // Check minimum order value
        if ($orderValue < $this->min_order_value) {
            return false;
        }

        // Check cutoff time for same-day delivery
        if ($this->cutoff_time && $options['order_time'] ?? null) {
            $orderTime = Carbon::parse($options['order_time'] ?? now());
            $cutoffTime = Carbon::parse($this->cutoff_time);
            
            if ($this->code === 'same_day' && $orderTime->format('H:i:s') > $cutoffTime->format('H:i:s')) {
                return false;
            }
        }

        // Check restricted days
        if ($this->restricted_days) {
            $orderDate = Carbon::parse($options['order_date'] ?? now());
            $dayOfWeek = $orderDate->dayOfWeek; // 0 = Sunday, 1 = Monday, etc.
            
            if (in_array($dayOfWeek, $this->restricted_days)) {
                return false;
            }
        }

        // Check availability conditions
        if ($this->availability_conditions) {
            return $this->checkAvailabilityConditions($zone, $orderValue, $options);
        }

        return true;
    }

    /**
     * Calculate delivery cost for this option
     */
    public function calculateCost(float $baseShippingCost, float $orderValue, array $options = []): array
    {
        $deliveryCost = ($baseShippingCost * $this->price_multiplier) + $this->fixed_surcharge;

        // Apply any conditional pricing
        if ($this->availability_conditions) {
            $deliveryCost = $this->applyConditionalPricing($deliveryCost, $orderValue, $options);
        }

        // Calculate delivery date range
        $orderDate = Carbon::parse($options['order_date'] ?? now());
        $minDeliveryDate = $this->calculateDeliveryDate($orderDate, $this->delivery_days_min, $options);
        $maxDeliveryDate = $this->calculateDeliveryDate($orderDate, $this->delivery_days_max, $options);

        return [
            'option_id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'cost' => round($deliveryCost, 2),
            'delivery_days_min' => $this->delivery_days_min,
            'delivery_days_max' => $this->delivery_days_max,
            'estimated_delivery_min' => $minDeliveryDate->format('Y-m-d'),
            'estimated_delivery_max' => $maxDeliveryDate->format('Y-m-d'),
            'delivery_window' => $this->getDeliveryWindow(),
        ];
    }

    /**
     * Get available delivery options for given conditions
     */
    public static function getAvailableOptions(string $zone, float $orderValue, float $baseShippingCost, array $options = []): array
    {
        $deliveryOptions = static::active()
            ->forZone($zone)
            ->forOrderValue($orderValue)
            ->orderedByPriority()
            ->get();

        $availableOptions = [];

        foreach ($deliveryOptions as $option) {
            if ($option->isAvailable($zone, $orderValue, $options)) {
                $availableOptions[] = $option->calculateCost($baseShippingCost, $orderValue, $options);
            }
        }

        return $availableOptions;
    }

    /**
     * Check availability conditions
     */
    protected function checkAvailabilityConditions(string $zone, float $orderValue, array $options = []): bool
    {
        foreach ($this->availability_conditions as $condition) {
            switch ($condition['type'] ?? null) {
                case 'metro_only':
                    if (!($options['is_metro'] ?? false)) {
                        return false;
                    }
                    break;

                case 'exclude_remote':
                    if ($options['is_remote'] ?? false) {
                        return false;
                    }
                    break;

                case 'weekday_only':
                    $orderDate = Carbon::parse($options['order_date'] ?? now());
                    if ($orderDate->isWeekend()) {
                        return false;
                    }
                    break;

                case 'high_value_only':
                    if ($orderValue < ($condition['threshold'] ?? 5000)) {
                        return false;
                    }
                    break;
            }
        }

        return true;
    }

    /**
     * Apply conditional pricing
     */
    protected function applyConditionalPricing(float $cost, float $orderValue, array $options = []): float
    {
        foreach ($this->availability_conditions as $condition) {
            switch ($condition['type'] ?? null) {
                case 'high_value_discount':
                    if ($orderValue >= ($condition['threshold'] ?? 10000)) {
                        $cost *= (1 - ($condition['discount_percent'] ?? 10) / 100);
                    }
                    break;

                case 'weekend_surcharge':
                    $orderDate = Carbon::parse($options['order_date'] ?? now());
                    if ($orderDate->isWeekend()) {
                        $cost += $condition['amount'] ?? 50;
                    }
                    break;

                case 'remote_surcharge':
                    if ($options['is_remote'] ?? false) {
                        $cost += $condition['amount'] ?? 100;
                    }
                    break;
            }
        }

        return $cost;
    }

    /**
     * Calculate delivery date considering business days and restrictions
     */
    protected function calculateDeliveryDate(Carbon $orderDate, int $deliveryDays, array $options = []): Carbon
    {
        $deliveryDate = $orderDate->copy();
        $daysAdded = 0;

        while ($daysAdded < $deliveryDays) {
            $deliveryDate->addDay();

            // Skip restricted days
            if ($this->restricted_days && in_array($deliveryDate->dayOfWeek, $this->restricted_days)) {
                continue;
            }

            // For business days only delivery, skip weekends
            if ($options['business_days_only'] ?? false) {
                if ($deliveryDate->isWeekend()) {
                    continue;
                }
            }

            $daysAdded++;
        }

        return $deliveryDate;
    }

    /**
     * Get human-readable delivery window
     */
    public function getDeliveryWindow(): string
    {
        if ($this->delivery_days_min === $this->delivery_days_max) {
            return $this->delivery_days_min === 1 
                ? '1 business day' 
                : "{$this->delivery_days_min} business days";
        }

        return "{$this->delivery_days_min}-{$this->delivery_days_max} business days";
    }

    protected static function booted()
    {
        static::saved(function ($option) {
            cache()->forget('delivery_options_' . $option->zone ?? 'all');
        });

        static::deleted(function ($option) {
            cache()->forget('delivery_options_' . $option->zone ?? 'all');
        });
    }
}