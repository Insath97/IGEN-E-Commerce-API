<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            [
                'key' => 'order_notification_enabled',
                'value' => '1',
            ],
            [
                'key' => 'order_notification_email',
                'value' => 'admin@gmail.com',
            ],
            [
                'key' => 'customer_order_notification_enabled',
                'value' => '1',
            ],
            [
                'key' => 'low_stock_alert_enabled',
                'value' => '1',
            ],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                ['value' => $setting['value']]
            );
        }
    }
}
