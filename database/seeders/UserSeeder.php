<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Seed additional operator users for local development testing.
     *
     * Reference users (super admin, admin, operator, driver, accounting) are
     * owned by the catalog and initialization migrations — this seeder only
     * adds extra operators to exercise multi-user scenarios locally. Drivers
     * con cuenta vienen del DriverSeeder vía el flag `_create_user`, así la
     * relación 1-1 User ↔ Driver se mantiene coherente desde el inicio.
     */
    public function run(): void
    {
        $defaultPassword = Hash::make('password');

        for ($i = 1; $i <= 3; $i++) {
            $user = User::firstOrCreate(
                ['email' => "operator{$i}@sgte.app"],
                [
                    'name' => "Operator User {$i}",
                    'password' => $defaultPassword,
                ],
            );

            if ($user->wasRecentlyCreated) {
                $user->forceFill(['email_verified_at' => now()])->save();
                $user->assignRole(Role::OPERATOR);
            }
        }
    }
}
