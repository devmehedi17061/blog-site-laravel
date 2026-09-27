<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Arif Rahman', 'email' => 'arif@example.com', 'role' => 'super-admin', 'status' => 'Active', 'last_seen_at' => now()->subMinutes(2)],
            ['name' => 'Mira Chowdhury', 'email' => 'mira@example.com', 'role' => 'editor', 'status' => 'Active', 'last_seen_at' => now()->subHours(4)],
            ['name' => 'Nadia Karim', 'email' => 'nadia@example.com', 'role' => 'author', 'status' => 'Active', 'last_seen_at' => now()->subDay()],
            ['name' => 'Tanvir Islam', 'email' => 'tanvir@example.com', 'role' => 'viewer', 'status' => 'Suspended', 'last_seen_at' => now()->subWeeks(6)],
        ];

        foreach ($users as $definition) {
            $role = Role::where('slug', $definition['role'])->first();
            unset($definition['role']);

            User::updateOrCreate(
                ['email' => $definition['email']],
                $definition + [
                    'role_id' => $role?->id,
                    'password' => 'password',
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}
