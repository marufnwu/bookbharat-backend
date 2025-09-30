<?php

use App\Models\AdminSetting;

if (!function_exists('setting')) {
    /**
     * Get a setting value by key
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function setting(string $key, $default = null)
    {
        return AdminSetting::get($key, $default);
    }
}

if (!function_exists('settings_group')) {
    /**
     * Get settings by group
     *
     * @param string $group
     * @return array
     */
    function settings_group(string $group): array
    {
        return AdminSetting::getByGroup($group);
    }
}

if (!function_exists('public_settings')) {
    /**
     * Get all public settings
     *
     * @return array
     */
    function public_settings(): array
    {
        return AdminSetting::where('is_public', true)
            ->get()
            ->keyBy('key')
            ->map(function ($setting) {
                return AdminSetting::get($setting->key);
            })
            ->toArray();
    }
}