<?php

namespace Database\Seeders;

use App\Models\Vehicle;
use App\Models\VehicleLocation;
use Illuminate\Database\Seeder;

class VehicleLocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (VehicleLocation::query()->exists()) {
            return;
        }

        $vehicles = Vehicle::where('status', 'active')->get();

        if ($vehicles->isEmpty()) {
            return;
        }

        $locations = [
            ['lat' => 4.60971000, 'lng' => -74.08175000],
            ['lat' => 4.71099000, 'lng' => -74.07210000],
            ['lat' => 6.25184000, 'lng' => -75.56359000],
            ['lat' => 7.12539000, 'lng' => -73.11980000],
        ];

        foreach ($vehicles as $index => $vehicle) {
            $loc = $locations[$index % count($locations)];

            VehicleLocation::create([
                'vehicle_id' => $vehicle->id,
                'recorded_at' => now(),
                'latitude' => $loc['lat'],
                'longitude' => $loc['lng'],
                'is_manual' => false,
            ]);
        }
    }
}
