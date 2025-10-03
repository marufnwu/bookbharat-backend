<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CarrierService extends Model
{
    use HasFactory;

    protected $fillable = [
        'carrier_id',
        'service_code',
        'service_name',
        'display_name',
        'description',
        'mode',
        'min_delivery_hours',
        'max_delivery_hours',
        'delivery_days',
        'cutoff_time',
        'supports_cod',
        'supports_insurance',
        'supports_doorstep_qc',
        'supports_doorstep_exchange',
        'supports_fragile',
        'pricing_tier',
        'base_weight_limit',
        'is_active',
        'priority'
    ];

    protected $casts = [
        'delivery_days' => 'array',
        'supports_cod' => 'boolean',
        'supports_insurance' => 'boolean',
        'supports_doorstep_qc' => 'boolean',
        'supports_doorstep_exchange' => 'boolean',
        'supports_fragile' => 'boolean',
        'is_active' => 'boolean',
        'base_weight_limit' => 'decimal:2'
    ];

    /**
     * Get the carrier that owns this service
     */
    public function carrier(): BelongsTo
    {
        return $this->belongsTo(ShippingCarrier::class, 'carrier_id');
    }

    /**
     * Get the rate cards for this service
     */
    public function rateCards(): HasMany
    {
        return $this->hasMany(CarrierRateCard::class, 'carrier_service_id');
    }

    /**
     * Get active rate cards
     */
    public function activeRateCards()
    {
        return $this->rateCards()
            ->where('is_active', true)
            ->where(function($query) {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', now());
            })
            ->where('effective_from', '<=', now());
    }

    /**
     * Calculate estimated delivery time
     */
    public function getEstimatedDeliveryTime(): string
    {
        if ($this->min_delivery_hours && $this->max_delivery_hours) {
            $minDays = ceil($this->min_delivery_hours / 24);
            $maxDays = ceil($this->max_delivery_hours / 24);

            if ($minDays == $maxDays) {
                return $minDays == 1 ? 'Next day' : "{$minDays} days";
            }

            return "{$minDays}-{$maxDays} days";
        }

        return 'Standard delivery';
    }

    /**
     * Check if service supports a feature
     */
    public function supports(string $feature): bool
    {
        $featureMap = [
            'cod' => $this->supports_cod,
            'insurance' => $this->supports_insurance,
            'doorstep_qc' => $this->supports_doorstep_qc,
            'doorstep_exchange' => $this->supports_doorstep_exchange,
            'fragile' => $this->supports_fragile
        ];

        return $featureMap[$feature] ?? false;
    }

    /**
     * Scope for active services
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by pricing tier
     */
    public function scopeByTier($query, string $tier)
    {
        return $query->where('pricing_tier', $tier);
    }

    /**
     * Get services ordered by priority
     */
    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'desc');
    }
}