<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\NotificationSetting;

class NotificationSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaultSettings = [
            [
                'event_type' => 'order_placed',
                'channels' => ['email', 'sms'],
                'enabled' => true,
            ],
            [
                'event_type' => 'order_confirmed',
                'channels' => ['email'],
                'enabled' => true,
            ],
            [
                'event_type' => 'order_shipped',
                'channels' => ['email', 'sms', 'whatsapp'],
                'enabled' => true,
            ],
            [
                'event_type' => 'order_delivered',
                'channels' => ['email', 'sms', 'whatsapp'],
                'enabled' => true,
            ],
            [
                'event_type' => 'order_cancelled',
                'channels' => ['email', 'sms'],
                'enabled' => true,
            ],
            [
                'event_type' => 'payment_success',
                'channels' => ['email'],
                'enabled' => true,
            ],
            [
                'event_type' => 'payment_failed',
                'channels' => ['email', 'sms'],
                'enabled' => true,
            ],
            [
                'event_type' => 'return_requested',
                'channels' => ['email'],
                'enabled' => true,
            ],
            [
                'event_type' => 'return_approved',
                'channels' => ['email', 'sms'],
                'enabled' => true,
            ],
            [
                'event_type' => 'return_completed',
                'channels' => ['email'],
                'enabled' => true,
            ],
            [
                'event_type' => 'abandoned_cart',
                'channels' => ['email'],
                'enabled' => true,
            ],
            [
                'event_type' => 'review_request',
                'channels' => ['email'],
                'enabled' => true,
            ],
            [
                'event_type' => 'password_reset',
                'channels' => ['email'],
                'enabled' => true,
            ],
            [
                'event_type' => 'welcome_email',
                'channels' => ['email'],
                'enabled' => true,
            ],
        ];

        foreach ($defaultSettings as $setting) {
            NotificationSetting::updateOrCreate(
                ['event_type' => $setting['event_type']],
                $setting
            );
        }

        $this->command->info('Notification settings seeded successfully');
    }
}

