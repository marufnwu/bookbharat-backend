<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'unique_keyword',
        'name',
        'description',
        'configuration',
        'is_active',
        'is_production',
        'supported_currencies',
        'webhook_config',
        'priority'
    ];

    protected $casts = [
        'configuration' => 'array',
        'supported_currencies' => 'array',
        'webhook_config' => 'array',
        'is_active' => 'boolean',
        'is_production' => 'boolean'
    ];

    /**
     * Get configuration data as array
     */
    public function convertJsonData(): array
    {
        return $this->configuration ?? [];
    }

    /**
     * Check if gateway supports a currency
     */
    public function supportsCurrency(string $currency): bool
    {
        if (empty($this->supported_currencies)) {
            return true; // No restrictions
        }

        return in_array($currency, $this->supported_currencies);
    }

    /**
     * Get active payment gateways
     */
    public static function getActiveGateways()
    {
        return self::where('is_active', true)
            ->orderBy('priority', 'asc')
            ->get();
    }

    /**
     * Get gateway by keyword
     */
    public static function getByKeyword(string $keyword)
    {
        return self::where('unique_keyword', $keyword)->first();
    }

    /**
     * Get configuration value by key
     */
    public function getConfig(string $key, $default = null)
    {
        return data_get($this->configuration, $key, $default);
    }
}