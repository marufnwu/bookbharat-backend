<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingZone extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipping_weight_slab_id',
        'zone',
        'fwd_rate',
        'rto_rate',
        'aw_rate',
        'cod_charges',
        'cod_percentage',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'fwd_rate'       => 'float',
        'rto_rate'       => 'float',
        'aw_rate'        => 'float',
        'cod_charges'    => 'float',
        'cod_percentage' => 'float',
    ];

    /**
     * Get the weight slab associated with the shipping zone.
     */
    public function weightSlab()
    {
        return $this->belongsTo(ShippingWeightSlab::class, 'shipping_weight_slab_id');
    }

    protected static function booted()
    {
        static::saved(function ($zone) {
            cache()->forget("shipping_slabs_zone_{$zone->zone}");
        });

        static::deleted(function ($zone) {
            cache()->forget("shipping_slabs_zone_{$zone->zone}");
        });
    }
}