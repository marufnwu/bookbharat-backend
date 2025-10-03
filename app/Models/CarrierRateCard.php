<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarrierRateCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'carrier_service_id',
        'zone_type',
        'source_region',
        'destination_region',
        'zone_code',
        'weight_min',
        'weight_max',
        'base_rate',
        'additional_per_kg',
        'additional_per_500g',
        'fuel_surcharge_percent',
        'gst_percent',
        'handling_charge',
        'oda_charge',
        'cod_charge_fixed',
        'cod_charge_percent',
        'min_cod_charge',
        'insurance_percent',
        'min_insurance_charge',
        'rto_charge',
        'rto_percent',
        'effective_from',
        'effective_to',
        'is_active'
    ];

    protected $casts = [
        'weight_min' => 'decimal:3',
        'weight_max' => 'decimal:3',
        'base_rate' => 'decimal:2',
        'additional_per_kg' => 'decimal:2',
        'additional_per_500g' => 'decimal:2',
        'fuel_surcharge_percent' => 'decimal:2',
        'gst_percent' => 'decimal:2',
        'handling_charge' => 'decimal:2',
        'oda_charge' => 'decimal:2',
        'cod_charge_fixed' => 'decimal:2',
        'cod_charge_percent' => 'decimal:2',
        'min_cod_charge' => 'decimal:2',
        'insurance_percent' => 'decimal:2',
        'min_insurance_charge' => 'decimal:2',
        'rto_charge' => 'decimal:2',
        'rto_percent' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean'
    ];

    /**
     * Get the carrier service that owns this rate card
     */
    public function carrierService(): BelongsTo
    {
        return $this->belongsTo(CarrierService::class, 'carrier_service_id');
    }

    /**
     * Calculate shipping charge for given weight and options
     */
    public function calculateCharge(float $weight, array $options = []): array
    {
        $charges = [];

        // Base charge calculation
        $baseCharge = $this->base_rate;

        // Additional weight charges
        if ($weight > $this->weight_min) {
            $additionalWeight = $weight - $this->weight_min;

            if ($this->additional_per_kg > 0) {
                $baseCharge += $additionalWeight * $this->additional_per_kg;
            } elseif ($this->additional_per_500g > 0) {
                $baseCharge += ceil($additionalWeight * 2) * $this->additional_per_500g;
            }
        }

        $charges['base'] = $baseCharge;

        // Fuel surcharge
        if ($this->fuel_surcharge_percent > 0) {
            $charges['fuel_surcharge'] = $baseCharge * ($this->fuel_surcharge_percent / 100);
        }

        // Handling charge
        if ($this->handling_charge > 0) {
            $charges['handling'] = $this->handling_charge;
        }

        // ODA charge (if applicable)
        if (!empty($options['is_oda']) && $this->oda_charge > 0) {
            $charges['oda'] = $this->oda_charge;
        }

        // COD charges
        if (!empty($options['is_cod'])) {
            $codAmount = $options['cod_amount'] ?? 0;
            $codCharge = $this->cod_charge_fixed;

            if ($this->cod_charge_percent > 0 && $codAmount > 0) {
                $codCharge += $codAmount * ($this->cod_charge_percent / 100);
            }

            $charges['cod'] = max($codCharge, $this->min_cod_charge);
        }

        // Insurance charges
        if (!empty($options['insurance_value']) && $options['insurance_value'] > 0) {
            $insuranceCharge = $options['insurance_value'] * ($this->insurance_percent / 100);
            $charges['insurance'] = max($insuranceCharge, $this->min_insurance_charge);
        }

        // Calculate subtotal (before GST)
        $subtotal = array_sum($charges);
        $charges['subtotal'] = $subtotal;

        // GST
        if ($this->gst_percent > 0) {
            $charges['gst'] = $subtotal * ($this->gst_percent / 100);
        }

        // Total
        $charges['total'] = $subtotal + ($charges['gst'] ?? 0);

        return $charges;
    }

    /**
     * Check if rate card is currently effective
     */
    public function isEffective(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = now();

        if ($this->effective_from && $this->effective_from > $now) {
            return false;
        }

        if ($this->effective_to && $this->effective_to < $now) {
            return false;
        }

        return true;
    }

    /**
     * Check if weight falls within this rate card's range
     */
    public function isWeightInRange(float $weight): bool
    {
        if ($weight < $this->weight_min) {
            return false;
        }

        if ($this->weight_max && $weight > $this->weight_max) {
            return false;
        }

        return true;
    }

    /**
     * Scope for active rate cards
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for currently effective rate cards
     */
    public function scopeEffective($query)
    {
        $now = now();

        return $query->active()
            ->where('effective_from', '<=', $now)
            ->where(function($q) use ($now) {
                $q->whereNull('effective_to')
                  ->orWhere('effective_to', '>=', $now);
            });
    }

    /**
     * Scope for weight range
     */
    public function scopeForWeight($query, float $weight)
    {
        return $query->where('weight_min', '<=', $weight)
            ->where(function($q) use ($weight) {
                $q->whereNull('weight_max')
                  ->orWhere('weight_max', '>=', $weight);
            });
    }

    /**
     * Scope for zone
     */
    public function scopeForZone($query, string $zone)
    {
        return $query->where('zone_code', $zone);
    }
}