<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\AdminSetting;

class SettingsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton('settings', function ($app) {
            return new class {
                public function get(string $key, $default = null) {
                    return AdminSetting::get($key, $default);
                }

                public function getByGroup(string $group): array {
                    return AdminSetting::getByGroup($group);
                }

                public function getPublic(): array {
                    return AdminSetting::where('is_public', true)
                        ->get()
                        ->keyBy('key')
                        ->map(function ($setting) {
                            return AdminSetting::get($setting->key);
                        })
                        ->toArray();
                }

                public function set(string $key, $value): bool {
                    return AdminSetting::set($key, $value);
                }
            };
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Share common settings with all views
        if ($this->app->runningInConsole() === false) {
            try {
                $publicSettings = AdminSetting::where('is_public', true)->get();
                $settings = [];

                foreach ($publicSettings as $setting) {
                    $settings[$setting->key] = AdminSetting::get($setting->key);
                }

                view()->share('settings', $settings);
                config(['app.settings' => $settings]);
            } catch (\Exception $e) {
                // Handle case where table doesn't exist yet (during migrations)
            }
        }
    }
}
