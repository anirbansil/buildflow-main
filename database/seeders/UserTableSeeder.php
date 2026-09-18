<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $superAdminUser = User::create([
            'first_name'        => 'Admin',
            'last_name'         => 'Admin',
            'email'             => 'admin@admin.com',
            'password'          => '11111111',
            'email_verified_at' => now(),
        ]);
        $superAdminUser->assignRole('SUPER-ADMIN');

        $user = User::create([
            'first_name'        => 'Demo',
            'last_name'         => 'Demo',
            'email'             => 'demo@yopmail.com',
            'password'          => '11111111',
            'email_verified_at' => now(),
        ]);
        $user->assignRole('OWNER');

        User::factory(5)->create()->each(function ($user) {
            $user->assignRole('SUPERVISOR');
        });
        User::factory(15)->create()->each(function ($user) {
            $user->assignRole('WORKER');
        });
    }
}
