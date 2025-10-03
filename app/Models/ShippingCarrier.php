<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingCarrier extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'display_name',
        'logo_url',
        'api_mode',
        'api_endpoint',
        'api_key',
        'api_secret',
        'client_name',
        'webhook_url',
        'supported_services',
        'features',
        'supported_payment_modes',
        'max_weight',
        'max_length',
        'max_width',
        'max_height',
        'max_volumetric_weight',
        'volumetric_divisor',
        'min_cod_amount',
        'max_cod_amount',
        'max_insurance_value',
        'prohibited_items',
        'restricted_pincodes',
        'pickup_locations',
        'return_address',
        'auto_generate_labels',
        'supports_reverse_pickup',
        'supports_qc_check',
        'supports_multi_piece',
        'is_active',
        'is_primary',
        'priority',
        'status',
        'avg_delivery_rating',
        'success_rate',
        'avg_delivery_hours',
        'config',
        'notes'
    ];

    protected $casts = [
        'supported_services' => 'array',
        'features' => 'array',
        'supported_payment_modes' => 'array',
        'prohibited_items' => 'array',
        'restricted_pincodes' => 'array',
        'pickup_locations' => 'array',
        'return_address' => 'array',
        'config' => 'array',
        'is_active' => 'boolean',
        'is_primary' => 'boolean',
        'auto_generate_labels' => 'boolean',
        'supports_reverse_pickup' => 'boolean',
        'supports_qc_check' => 'boolean',
        'supports_multi_piece' => 'boolean',
        'max_weight' => 'decimal:2',
        'max_length' => 'decimal:2',
        'max_width' => 'decimal:2',
        'max_height' => 'decimal:2',
        'max_volumetric_weight' => 'decimal:2',
        'min_cod_amount' => 'decimal:2',
        'max_cod_amount' => 'decimal:2',
        'max_insurance_value' => 'decimal:2',
        'avg_delivery_rating' => 'decimal:2',
        'success_rate' => 'decimal:2'
    ];

    /**
     * Get the carrier services for this carrier
     */
    public function services(): HasMany
    {
        return $this->hasMany(CarrierService::class, 'carrier_id');
    }

    /**
     * Get the pincode serviceability for this carrier
     */
    public function pincodeServiceability(): HasMany
    {
        return $this->hasMany(CarrierPincodeServiceability::class, 'carrier_id');
    }

    /**
     * Get the API logs for this carrier
     */
    public function apiLogs(): HasMany
    {
        return $this->hasMany(CarrierApiLog::class, 'carrier_id');
    }

    /**
     * Get active services
     */
    public function activeServices()
    {
        return $this->services()->where('is_active', true)->orderBy('priority', 'desc');
    }

    /**
     * Check if carrier supports a specific feature
     */
    public function supportsFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? []);
    }

    /**
     * Check if carrier supports a specific payment mode
     */
    public function supportsPaymentMode(string $mode): bool
    {
        return in_array($mode, $this->supported_payment_modes ?? []);
    }

    /**
     * Check if carrier supports a specific service
     */
    public function supportsService(string $service): bool
    {
        return in_array($service, $this->supported_services ?? []);
    }

    /**
     * Get decrypted API key
     */
    public function getApiKeyAttribute($value)
    {
        // In production, decrypt this value
        // return decrypt($value);
        return $value;
    }

    /**
     * Set encrypted API key
     */
    public function setApiKeyAttribute($value)
    {
        // In production, encrypt this value
        // $this->attributes['api_key'] = encrypt($value);
        $this->attributes['api_key'] = $value;
    }

    /**
     * Get decrypted API secret
     */
    public function getApiSecretAttribute($value)
    {
        // In production, decrypt this value
        // return decrypt($value);
        return $value;
    }

    /**
     * Set encrypted API secret
     */
    public function setApiSecretAttribute($value)
    {
        // In production, encrypt this value
        // $this->attributes['api_secret'] = encrypt($value);
        $this->attributes['api_secret'] = $value;
    }

    /**
     * Scope for active carriers
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('status', 'active');
    }

    /**
     * Scope for primary carriers
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Get carriers ordered by priority
     */
    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'desc');
    }
}