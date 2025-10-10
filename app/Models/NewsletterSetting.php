<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class NewsletterSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'group',
        'description',
    ];

    /**
     * Get a setting value by key
     */
    public static function getValue($key, $default = null)
    {
        return Cache::remember("newsletter_setting_{$key}", 3600, function () use ($key, $default) {
            $setting = self::where('key', $key)->first();
            return $setting ? $setting->value : $default;
        });
    }

    /**
     * Set a setting value
     */
    public static function setValue($key, $value, $group = 'general', $description = null)
    {
        $setting = self::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'group' => $group,
                'description' => $description,
            ]
        );

        Cache::forget("newsletter_setting_{$key}");
        Cache::forget('newsletter_settings');

        return $setting;
    }

    /**
     * Get all settings grouped
     */
    public static function getAllGrouped()
    {
        return Cache::remember('newsletter_settings', 3600, function () {
            return self::all()->groupBy('group');
        });
    }
}

