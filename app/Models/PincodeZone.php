<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class PincodeZone extends Model
{
    use HasFactory;

    protected $fillable = [
        'pincode',
        'zone',
        'city',
        'state',
        'region',
        'is_metro',
        'is_remote',
        'cod_available',
        'expected_delivery_days',
        'zone_multiplier',
    ];

    protected $casts = [
        'is_metro' => 'boolean',
        'is_remote' => 'boolean',
        'cod_available' => 'boolean',
        'expected_delivery_days' => 'integer',
        'zone_multiplier' => 'decimal:2',
    ];

    public function scopeByPincode(Builder $query, string $pincode)
    {
        return $query->where('pincode', $pincode);
    }

    public function scopeByZone(Builder $query, string $zone)
    {
        return $query->where('zone', $zone);
    }

    public function scopeMetro(Builder $query)
    {
        return $query->where('is_metro', true);
    }

    public function scopeRemote(Builder $query)
    {
        return $query->where('is_remote', true);
    }

    public function scopeCodEnabled(Builder $query)
    {
        return $query->where('cod_available', true);
    }

    public function scopeByState(Builder $query, string $state)
    {
        return $query->where('state', $state);
    }

    public static function getZoneByPincode(string $pincode): ?string
    {
        return cache()->remember("pincode_zone_{$pincode}", 3600, function () use ($pincode) {
            $pincodeZone = static::byPincode($pincode)->first();
            return $pincodeZone ? $pincodeZone->zone : null;
        });
    }

    public static function getPincodeDetails(string $pincode): ?array
    {
        return cache()->remember("pincode_details_{$pincode}", 3600, function () use ($pincode) {
            $pincodeZone = static::byPincode($pincode)->first();
            
            if (!$pincodeZone) {
                return null;
            }

            return [
                'zone' => $pincodeZone->zone,
                'city' => $pincodeZone->city,
                'state' => $pincodeZone->state,
                'region' => $pincodeZone->region,
                'is_metro' => $pincodeZone->is_metro,
                'is_remote' => $pincodeZone->is_remote,
                'cod_available' => $pincodeZone->cod_available,
                'expected_delivery_days' => $pincodeZone->expected_delivery_days,
                'zone_multiplier' => $pincodeZone->zone_multiplier,
            ];
        });
    }

    public static function isServiceable(string $pincode): bool
    {
        return static::byPincode($pincode)->exists();
    }

    public static function isCodAvailable(string $pincode): bool
    {
        $details = static::getPincodeDetails($pincode);
        return $details ? $details['cod_available'] : false;
    }

    public static function getDeliveryDays(string $pincode): int
    {
        $details = static::getPincodeDetails($pincode);
        return $details ? $details['expected_delivery_days'] : 7; // Default 7 days
    }

    protected static function booted()
    {
        static::saved(function ($pincodeZone) {
            cache()->forget("pincode_zone_{$pincodeZone->pincode}");
            cache()->forget("pincode_details_{$pincodeZone->pincode}");
        });

        static::deleted(function ($pincodeZone) {
            cache()->forget("pincode_zone_{$pincodeZone->pincode}");
            cache()->forget("pincode_details_{$pincodeZone->pincode}");
        });
    }
}
