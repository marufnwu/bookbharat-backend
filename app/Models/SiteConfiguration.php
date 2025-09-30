<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteConfiguration extends Model
{
    protected $fillable = [
        'key',
        'value',
        'group',
        'description'
    ];

    protected $casts = [
        'value' => 'array'
    ];

    /**
     * Get configuration value by key
     */
    public static function getValue(string $key, $default = null)
    {
        $config = static::where('key', $key)->first();
        return $config ? $config->value : $default;
    }

    /**
     * Set configuration value
     */
    public static function setValue(string $key, $value, string $group = 'general', string $description = null)
    {
        return static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'group' => $group,
                'description' => $description
            ]
        );
    }

    /**
     * Get configurations by group
     */
    public static function getByGroup(string $group)
    {
        return static::where('group', $group)->get()->pluck('value', 'key');
    }

    /**
     * Update multiple configurations
     */
    public static function updateMultiple(array $configs, string $group = 'general')
    {
        foreach ($configs as $key => $value) {
            static::setValue($key, $value, $group);
        }
    }
}
