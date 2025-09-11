<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class InventoryLocation extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'name',
        'code',
        'type',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'latitude',
        'longitude',
        'contact_person',
        'contact_phone',
        'contact_email',
        'is_active',
        'is_default',
        'settings',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'settings' => 'array',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable();
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class, 'location_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'location_id');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(StockAlert::class, 'location_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function getFullAddressAttribute()
    {
        return implode(', ', array_filter([
            $this->address,
            $this->city,
            $this->state,
            $this->postal_code,
            $this->country
        ]));
    }

    public function getTotalProductsAttribute()
    {
        return $this->inventoryItems()
            ->where('available_quantity', '>', 0)
            ->distinct('product_id')
            ->count('product_id');
    }

    public function getTotalStockValueAttribute()
    {
        return $this->inventoryItems()
            ->selectRaw('SUM(available_quantity * unit_cost)')
            ->value('SUM(available_quantity * unit_cost)') ?? 0;
    }
}