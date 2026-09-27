<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            AdminUserSeeder::class,
            SectionLibrarySeeder::class,
            FormSeeder::class,
            ContentSeeder::class,
            MenuSeeder::class,
            SettingsSeeder::class,
        ]);
    }
}
