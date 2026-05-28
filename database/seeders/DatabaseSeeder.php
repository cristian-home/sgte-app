<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with test data for local development.
     *
     * Three-tier seeding strategy:
     *
     * - Catalog migration (all envs): roles, permissions, super admin, catalogs, geography
     * - Initialization migration (local only): reference users, third parties, drivers, vehicles
     * - Seeders (here, local only via `db:seed`): operational/transactional test data
     *
     * All seeders are idempotent (firstOrCreate or early-return guards) and safe to re-run.
     * May use factories/Faker — seeders are never executed in production or staging.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            BillingGroupSeeder::class,
            ContractSeeder::class,
            InvoiceSeeder::class,
            DayStatusSeeder::class,
            ServiceSeeder::class,
            ServiceIncidentSeeder::class,
            FuecSeeder::class,
            VehicleLocationSeeder::class,
        ]);
    }
}
