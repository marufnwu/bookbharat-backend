<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Warehouse extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'contact_person',
        'phone',
        'email',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'pincode',
        'country',
        'latitude',
        'longitude',
        'is_active',
        'is_default',
        'gst_number',
        'carrier_settings',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'carrier_settings' => 'array',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    /**
     * Get carriers associated with this warehouse
     */
    public function carriers(): BelongsToMany
    {
        return $this->belongsToMany(
            ShippingCarrier::class,
            'carrier_warehouse',
            'warehouse_id',
            'carrier_id'
        )
            ->withPivot('carrier_warehouse_id', 'carrier_warehouse_name', 'is_enabled')
            ->withTimestamps();
    }

    /**
     * Get carrier-specific warehouse configuration
     */
    public function getCarrierConfig(int $carrierId): ?array
    {
        $pivot = $this->carriers()->where('carrier_id', $carrierId)->first()?->pivot;

        if (!$pivot) {
            return null;
        }

        return [
            'carrier_warehouse_id' => $pivot->carrier_warehouse_id,
            'carrier_warehouse_name' => $pivot->carrier_warehouse_name,
            'is_enabled' => $pivot->is_enabled,
        ];
    }

    /**
     * Get warehouse as pickup address array
     */
    public function toPickupAddress(): array
    {
        return [
            'name' => $this->name,
            'contact_person' => $this->contact_person,
            'phone' => $this->phone,
            'email' => $this->email,
            'address_1' => $this->address_line_1,
            'address_2' => $this->address_line_2,
            'city' => $this->city,
            'state' => $this->state,
            'pincode' => $this->pincode,
            'country' => $this->country,
        ];
    }

    /**
     * Scope for active warehouses
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for default warehouse
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}
