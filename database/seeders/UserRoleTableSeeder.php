<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class UserRoleTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $userTypes = ['SUPER-ADMIN', 'OWNER', 'SUPERVISOR', 'WORKER'];

        foreach ($userTypes as $userType) {
            Role::create(['name' => $userType]);
        }
    }
}
