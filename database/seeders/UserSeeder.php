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
        // Super Admin user
        $superAdminUser = User::factory()->create([
            'name' => 'Super Admin User',
            'email' => 'superadmin@example.com',
            'password' => bcrypt('password'),
        ]);

        // Admin user
        $adminUser = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);

        // Operator users
        $operatorUsers = User::factory(5)->sequence(
            fn ($sequence) => [
                'name' => 'Operator User '.($sequence->index + 1),
                'password' => bcrypt('password'),
            ]
        )->create();

        // Driver users
        $driverUsers = User::factory(5)->sequence(
            fn ($sequence) => [
                'name' => 'Driver User '.($sequence->index + 1),
                'email_verified_at' => null,
                'password' => bcrypt('password'),
            ]
        )->create();

        // Accounting users
        $accountingUsers = User::factory(5)->sequence(
            fn ($sequence) => [
                'name' => 'Accounting User '.($sequence->index + 1),
                'email_verified_at' => null,
                'password' => bcrypt('password'),
            ]
        )->create();

        $superAdminUser->assignRole(Role::SUPER_ADMIN);

        $adminUser->assignRole(Role::ADMIN);

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
