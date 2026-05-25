<?php

use App\Enums\Role;
use App\Models\Driver;
use App\Models\User;
use Database\Seeders\DriverSeeder;
use Database\Seeders\ThirdPartySeeder;
use Database\Seeders\VehicleSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Hash;

/**
 * Initialization data migration — runs in local environment only.
 *
 * Seeds structural reference data that the application needs to be usable
 * in local development: reference users (admin, operator, driver, accounting),
 * third parties, drivers, and vehicles.
 *
 * Skips in production, staging, and testing. Test data (contracts, invoices,
 * day statuses, services, incidents, locations, FUECs) is handled by the
 * DatabaseSeeder and must be triggered manually via `php artisan db:seed`.
 *
 * All operations use firstOrCreate to be idempotent and safe to re-run.
 * Must not depend on Faker or any `require-dev` package.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->environment('production', 'staging', 'testing')) {
            return;
        }

        $this->seedReferenceUsers();
        (new ThirdPartySeeder)->run();
        (new DriverSeeder)->run();
        (new VehicleSeeder)->run();

        // Link the reference driver user (driver@sgte.app) to the first
        // Driver record so DriverDashboardController can resolve
        // $user->driver and show their assigned services.
        $driverUser = User::where('email', 'driver@sgte.app')->first();
        if ($driverUser) {
            $unlinkedDriver = Driver::whereNull('user_id')->orderBy('id')->first();
            $unlinkedDriver?->update(['user_id' => $driverUser->id]);
        }
    }

    public function down(): void
    {
        // Initialization data should not be rolled back.
    }

    private function seedReferenceUsers(): void
    {
        $defaultPassword = Hash::make('password');

        $users = [
            ['email' => 'admin@sgte.app', 'name' => 'Admin User', 'role' => Role::ADMIN],
            ['email' => 'operator@sgte.app', 'name' => 'Operator User', 'role' => Role::OPERATOR],
            ['email' => 'driver@sgte.app', 'name' => 'Driver User', 'role' => Role::DRIVER],
            ['email' => 'accounting@sgte.app', 'name' => 'Accounting User', 'role' => Role::ACCOUNTING],
        ];

        foreach ($users as $userData) {
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => $defaultPassword,
                ],
            );

            if ($user->wasRecentlyCreated) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            $user->assignRole($userData['role']);
        }
    }
};
