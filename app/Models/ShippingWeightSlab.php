<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ShippingWeightSlab extends Model
{
    use HasFactory;

    protected $fillable = [
        'courier_name',
        'base_weight',
    ];

    protected $casts = [
        'base_weight' => 'decimal:2',
    ];

    /**
     * Get the shipping zones for this weight slab.
     */
    public function shippingZones()
    {
        return $this->hasMany(ShippingZone::class, 'shipping_weight_slab_id');
    }

    public function scopeForCourier(Builder $query, string $courier = null)
    {
        if ($courier) {
            return $query->where('courier_name', $courier);
        }
        return $query;
    }

    public function scopeForWeight(Builder $query, float $weight)
    {
        return $query->where('base_weight', '<=', $weight)
                    ->orderBy('base_weight', 'desc');
    }

    protected static function booted()
    {
        static::saved(function ($slab) {
            cache()->flush(); // Clear related caches
        });

        static::deleted(function ($slab) {
            cache()->flush();
        });
    }
}