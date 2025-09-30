<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AdminSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'label',
        'description',
        'options',
        'is_public',
        'is_editable',
        'input_type',
        'sort_order'
    ];

    protected $casts = [
        'options' => 'array',
        'is_public' => 'boolean',
        'is_editable' => 'boolean',
        'sort_order' => 'integer'
    ];

    /**
     * Get a setting value by key
     */
    public static function get(string $key, $default = null)
    {
        $cacheKey = "admin_setting.{$key}";

        return Cache::remember($cacheKey, 3600, function () use ($key, $default) {
            $setting = static::where('key', $key)->first();

            if (!$setting) {
                return $default;
            }

            return static::castValue($setting->value, $setting->type);
        });
    }

    /**
     * Set a setting value by key
     */
    public static function set(string $key, $value): bool
    {
        $setting = static::where('key', $key)->first();

        if (!$setting) {
            return false; // Setting doesn't exist
        }

        $setting->update([
            'value' => static::formatValue($value, $setting->type)
        ]);

        // Clear cache
        Cache::forget("admin_setting.{$key}");
        Cache::forget('admin_settings_by_group');

        return true;
    }

    /**
     * Get settings by group
     */
    public static function getByGroup(string $group): array
    {
        $cacheKey = "admin_settings_group.{$group}";

        return Cache::remember($cacheKey, 3600, function () use ($group) {
            $settings = static::where('group', $group)
                ->orderBy('sort_order')
                ->orderBy('label')
                ->get();

            $result = [];
            foreach ($settings as $setting) {
                $result[$setting->key] = static::castValue($setting->value, $setting->type);
            }

            return $result;
        });
    }

    /**
     * Get all settings grouped by group
     */
    public static function getAllGrouped(): array
    {
        return Cache::remember('admin_settings_by_group', 3600, function () {
            $settings = static::orderBy('group')->orderBy('sort_order')->get();

            $grouped = [];
            foreach ($settings as $setting) {
                $grouped[$setting->group][$setting->key] = [
                    'value' => static::castValue($setting->value, $setting->type),
                    'label' => $setting->label,
                    'description' => $setting->description,
                    'type' => $setting->type,
                    'input_type' => $setting->input_type,
                    'options' => $setting->options,
                    'is_editable' => $setting->is_editable,
                    'is_public' => $setting->is_public,
                ];
            }

            return $grouped;
        });
    }

    /**
     * Cast value to proper type
     */
    private static function castValue($value, string $type)
    {
        switch ($type) {
            case 'boolean':
                return (bool) $value;
            case 'integer':
                return (int) $value;
            case 'float':
            case 'double':
                return (float) $value;
            case 'array':
            case 'json':
                return is_string($value) ? json_decode($value, true) : $value;
            case 'string':
            default:
                return (string) $value;
        }
    }

    /**
     * Format value for storage
     */
    private static function formatValue($value, string $type): string
    {
        switch ($type) {
            case 'boolean':
                return $value ? '1' : '0';
            case 'array':
            case 'json':
                return json_encode($value);
            default:
                return (string) $value;
        }
    }

    /**
     * Clear all settings cache
     */
    public static function clearCache(): void
    {
        $keys = static::pluck('key');

        foreach ($keys as $key) {
            Cache::forget("admin_setting.{$key}");
        }

        Cache::forget('admin_settings_by_group');

        // Clear group caches
        $groups = static::distinct('group')->pluck('group');
        foreach ($groups as $group) {
            Cache::forget("admin_settings_group.{$group}");
        }
    }
}
