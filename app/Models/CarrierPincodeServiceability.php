<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarrierPincodeServiceability extends Model
{
    use HasFactory;

    protected $table = 'carrier_pincode_serviceability';

    protected $fillable = [
        'carrier_id',
        'pincode',
        'city',
        'state',
        'zone',
        'region',
        'area_type',
        'is_serviceable',
        'is_cod_available',
        'is_prepaid_available',
        'is_pickup_available',
        'is_reverse_pickup',
        'is_oda',
        'standard_delivery_days',
        'express_delivery_days',
        'cutoff_time',
        'delivery_days',
        'oda_charge',
        'area_surcharge',
        'max_weight',
        'max_cod_amount',
        'restricted_items',
        'last_updated'
    ];

    protected $casts = [
        'is_serviceable' => 'boolean',
        'is_cod_available' => 'boolean',
        'is_prepaid_available' => 'boolean',
        'is_pickup_available' => 'boolean',
        'is_reverse_pickup' => 'boolean',
        'is_oda' => 'boolean',
        'delivery_days' => 'array',
        'restricted_items' => 'array',
        'oda_charge' => 'decimal:2',
        'area_surcharge' => 'decimal:2',
        'max_weight' => 'decimal:2',
        'max_cod_amount' => 'decimal:2',
        'last_updated' => 'datetime'
    ];

    /**
     * Get the carrier that owns this serviceability
     */
    public function carrier(): BelongsTo
    {
        return $this->belongsTo(ShippingCarrier::class, 'carrier_id');
    }

    /**
     * Check if pincode is serviceable for given payment mode
     */
    public function isServiceableFor(string $paymentMode): bool
    {
        if (!$this->is_serviceable) {
            return false;
        }

        if ($paymentMode === 'cod') {
            return $this->is_cod_available;
        }

        return $this->is_prepaid_available;
    }

    /**
     * Get additional charges for this pincode
     */
    public function getAdditionalCharges(): float
    {
        $charges = 0;

        if ($this->is_oda) {
            $charges += $this->oda_charge ?? 0;
        }

        $charges += $this->area_surcharge ?? 0;

        return $charges;
    }

    /**
     * Check if item is restricted
     */
    public function isItemRestricted(string $itemType): bool
    {
        if (empty($this->restricted_items)) {
            return false;
        }

        return in_array($itemType, $this->restricted_items);
    }

    /**
     * Get estimated delivery days
     */
    public function getEstimatedDeliveryDays(string $serviceType = 'standard'): ?int
    {
        if ($serviceType === 'express') {
            return $this->express_delivery_days;
        }

        return $this->standard_delivery_days;
    }

    /**
     * Scope for serviceable pincodes
     */
    public function scopeServiceable($query)
    {
        return $query->where('is_serviceable', true);
    }

    /**
     * Scope for COD available pincodes
     */
    public function scopeCodAvailable($query)
    {
        return $query->where('is_cod_available', true);
    }

    /**
     * Scope for prepaid available pincodes
     */
    public function scopePrepaidAvailable($query)
    {
        return $query->where('is_prepaid_available', true);
    }

    /**
     * Scope for pickup available pincodes
     */
    public function scopePickupAvailable($query)
    {
        return $query->where('is_pickup_available', true);
    }

    /**
     * Scope for ODA pincodes
     */
    public function scopeOda($query)
    {
        return $query->where('is_oda', true);
    }

    /**
     * Scope for non-ODA pincodes
     */
    public function scopeNonOda($query)
    {
        return $query->where('is_oda', false);
    }

    /**
     * Scope by area type
     */
    public function scopeAreaType($query, string $type)
    {
        return $query->where('area_type', $type);
    }

    /**
     * Scope by state
     */
    public function scopeByState($query, string $state)
    {
        return $query->where('state', $state);
    }

    /**
     * Scope by city
     */
    public function scopeByCity($query, string $city)
    {
        return $query->where('city', $city);
    }
}