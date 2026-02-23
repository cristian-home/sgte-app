<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        // Admin user
        $adminUser = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password')
        ]);

        $adminUser->assignRole(Role::ADMIN);

        $operatorUsers = User::factory(5)->create([
            'name' => 'Operator User',
            'password' => bcrypt('password')
        ]);

        $driverUsers = User::factory(5)->create([
            'name' => 'Driver User',
            'password' => bcrypt('password')
        ]);

        $accountingUsers = User::factory(5)->create([
            'name' => 'Accounting User',
            'password' => bcrypt('password')
        ]);

        foreach ($operatorUsers as $operatorUser) {
            $operatorUser->assignRole(Role::OPERATOR);
        }

        foreach ($driverUsers as $driverUser) {
            $driverUser->assignRole(Role::DRIVER);
        }

        foreach ($accountingUsers as $accountingUser) {
            $accountingUser->assignRole(Role::ACCOUNTING);
        }
    }
}
