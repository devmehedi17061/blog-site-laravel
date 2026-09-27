<?php

namespace Database\Seeders;

use App\Models\IpRule;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $general = [
            'app_name' => 'Northwind CMS',
            'app_url' => 'https://admin.northwind.test',
            'timezone' => 'Asia/Dhaka',
            'locale' => 'en',
            'mail_from' => 'no-reply@northwind.test',
            'per_page' => 25,
            'cache_driver' => 'redis',
            'queue_driver' => 'database',
        ];

        foreach ($general as $key => $value) {
            Setting::put($key, $value, 'general');
        }

        $security = [
            'two_factor' => true,
            'force_https' => true,
            'lockout' => true,
            'single_session' => false,
            'maintenance' => false,
            'password_min' => 12,
            'password_rotation_days' => 90,
            'max_attempts' => 5,
        ];

        foreach ($security as $key => $value) {
            Setting::put($key, $value, 'security');
        }

        $ips = [
            ['value' => '203.0.113.0/24', 'type' => 'allow', 'note' => 'Office network'],
            ['value' => '198.51.100.17', 'type' => 'allow', 'note' => 'Deploy runner'],
            ['value' => '10.0.0.0/8', 'type' => 'allow', 'note' => 'Internal VPN'],
        ];

        foreach ($ips as $ip) {
            IpRule::updateOrCreate(['value' => $ip['value']], $ip);
        }
    }
}
