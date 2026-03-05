<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            UserSeeder::class,
            DepartmentAndMunicipalitySeeder::class,
            DocumentTypeSeeder::class,
            EpsSeeder::class,
            PensionFundSeeder::class,
            SeveranceFundSeeder::class,
            ThirdPartySeeder::class,
            DriverSeeder::class,
            VehicleSeeder::class,
            ContractSeeder::class,
            InvoiceSeeder::class,
            DayStatusSeeder::class,
            ServiceSeeder::class,
            IncidentTypeSeeder::class,
            ServiceIncidentSeeder::class,
            FuecSeeder::class,
            VehicleLocationSeeder::class,
        ]);
    }
}
