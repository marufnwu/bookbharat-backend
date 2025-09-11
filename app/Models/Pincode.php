<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Pincode extends Model
{
    use HasFactory;

    protected $table = 'pin_codes';

    protected $fillable = [
        'circlename',
        'regionname',
        'divisionname',
        'officename',
        'pincode',
        'officetype',
        'delivery',
        'district',
        'statename',
        'city',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function scopeByPincode(Builder $query, string $pincode)
    {
        return $query->where('pincode', $pincode);
    }

    public function scopeByCity(Builder $query, string $city)
    {
        return $query->where('city', 'like', "%{$city}%");
    }

    public function scopeByState(Builder $query, string $state)
    {
        return $query->where('statename', 'like', "%{$state}%");
    }

    public function scopeByDistrict(Builder $query, string $district)
    {
        return $query->where('district', 'like', "%{$district}%");
    }

    public function scopeDeliveryOffices(Builder $query)
    {
        return $query->where('delivery', 'Delivery');
    }

    public function scopeHeadOffices(Builder $query)
    {
        return $query->where('officetype', 'Head Office');
    }

    public static function getPincodeDetails(string $pincode): ?array
    {
        return cache()->remember("pincode_details_{$pincode}", 3600, function () use ($pincode) {
            $pincodeData = static::byPincode($pincode)->first();
            
            if (!$pincodeData) {
                return null;
            }

            return [
                'pincode' => $pincodeData->pincode,
                'city' => $pincodeData->city,
                'district' => $pincodeData->district,
                'state' => $pincodeData->statename,
                'region' => $pincodeData->regionname,
                'circle' => $pincodeData->circlename,
                'division' => $pincodeData->divisionname,
                'office_name' => $pincodeData->officename,
                'office_type' => $pincodeData->officetype,
                'delivery_status' => $pincodeData->delivery,
                'latitude' => $pincodeData->latitude,
                'longitude' => $pincodeData->longitude,
            ];
        });
    }

    public static function isServiceable(string $pincode): bool
    {
        return cache()->remember("pincode_serviceable_{$pincode}", 3600, function () use ($pincode) {
            return static::byPincode($pincode)->deliveryOffices()->exists();
        });
    }

    public static function getCityByPincode(string $pincode): ?string
    {
        return cache()->remember("pincode_city_{$pincode}", 3600, function () use ($pincode) {
            $pincodeData = static::byPincode($pincode)->first();
            return $pincodeData ? $pincodeData->city : null;
        });
    }

    public static function getStateByPincode(string $pincode): ?string
    {
        return cache()->remember("pincode_state_{$pincode}", 3600, function () use ($pincode) {
            $pincodeData = static::byPincode($pincode)->first();
            return $pincodeData ? $pincodeData->statename : null;
        });
    }

    public static function getDistrictByPincode(string $pincode): ?string
    {
        return cache()->remember("pincode_district_{$pincode}", 3600, function () use ($pincode) {
            $pincodeData = static::byPincode($pincode)->first();
            return $pincodeData ? $pincodeData->district : null;
        });
    }

    public static function searchPincodesByCity(string $city, int $limit = 10): array
    {
        return static::byCity($city)
            ->select('pincode', 'city', 'district', 'statename')
            ->distinct()
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public static function isDeliveryAvailable(string $pincode): bool
    {
        return cache()->remember("pincode_delivery_{$pincode}", 3600, function () use ($pincode) {
            return static::byPincode($pincode)
                ->where('delivery', 'Delivery')
                ->exists();
        });
    }

    public function getCoordinatesAttribute(): ?array
    {
        if ($this->latitude && $this->longitude) {
            return [
                'lat' => (float) $this->latitude,
                'lng' => (float) $this->longitude
            ];
        }
        return null;
    }

    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->city,
            $this->district,
            $this->statename,
            $this->pincode
        ]);
        
        return implode(', ', $parts);
    }

    protected static function booted()
    {
        static::saved(function ($pincode) {
            cache()->forget("pincode_details_{$pincode->pincode}");
            cache()->forget("pincode_serviceable_{$pincode->pincode}");
            cache()->forget("pincode_city_{$pincode->pincode}");
            cache()->forget("pincode_state_{$pincode->pincode}");
            cache()->forget("pincode_district_{$pincode->pincode}");
            cache()->forget("pincode_delivery_{$pincode->pincode}");
        });

        static::deleted(function ($pincode) {
            cache()->forget("pincode_details_{$pincode->pincode}");
            cache()->forget("pincode_serviceable_{$pincode->pincode}");
            cache()->forget("pincode_city_{$pincode->pincode}");
            cache()->forget("pincode_state_{$pincode->pincode}");
            cache()->forget("pincode_district_{$pincode->pincode}");
            cache()->forget("pincode_delivery_{$pincode->pincode}");
        });
    }
}