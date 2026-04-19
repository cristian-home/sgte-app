<?php

namespace Database\Seeders;

use App\Enums\ServiceStatus;
use App\Models\Service;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleLocation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Seeds vehicle location data so the /gps/map view renders meaningful
 * markers immediately after `migrate:fresh --seed`.
 *
 * Strategy:
 *   1. Promote up to 4 existing services to today + open status (if no
 *      services already exist for today). Without this, /gps/map is
 *      empty because it filters by `service_date = today() AND
 *      service_status = open`.
 *   2. Create a service-scoped VehicleLocation per promoted service
 *      with coordinates spread around Medellín + a couple of other
 *      Colombian cities so the map is visually interesting.
 *   3. Also keep the original "vehicle-scoped historical" rows as a
 *      fallback dataset for the 24h fallback path.
 */
class VehicleLocationSeeder extends Seeder
{
    public function run(): void
    {
        if (VehicleLocation::query()->exists()) {
            return;
        }

        $vehicles = Vehicle::where('status', 'active')->get();

        if ($vehicles->isEmpty()) {
            return;
        }

        $admin = User::query()->where('email', 'admin@sgte.app')->first();

        // 1. Ensure there are 4 open services for today the map can plot.
        $today = Carbon::today()->toDateString();
        $services = Service::query()
            ->where('service_status', ServiceStatus::Open)
            ->whereDate('service_date', $today)
            ->get();

        if ($services->count() < 4) {
            $needed = 4 - $services->count();
            $promoted = Service::query()
                ->whereNotIn('id', $services->pluck('id'))
                ->orderByDesc('id')
                ->limit($needed)
                ->get();

            foreach ($promoted as $service) {
                $service->update([
                    'service_date' => $today,
                    'service_status' => ServiceStatus::Open,
                ]);
            }

            $services = $services->merge($promoted);
        }

        // 2. Coordinates spread around Medellín (the company's primary
        //    operational area) plus Bogotá + Bucaramanga so the auto-fit
        //    bounds don't zoom too tight on a single pin.
        $coordinates = [
            ['lat' => 6.25184000, 'lng' => -75.56359000, 'manual' => false, 'accuracy' => 12],
            ['lat' => 6.20107000, 'lng' => -75.57826000, 'manual' => false, 'accuracy' => 8],
            ['lat' => 6.30000000, 'lng' => -75.50000000, 'manual' => true, 'accuracy' => null],
            ['lat' => 4.71099000, 'lng' => -74.07210000, 'manual' => false, 'accuracy' => 25],
            ['lat' => 7.12539000, 'lng' => -73.11980000, 'manual' => false, 'accuracy' => 18],
        ];

        foreach ($services->values() as $index => $service) {
            $coord = $coordinates[$index % count($coordinates)];

            VehicleLocation::create([
                'vehicle_id' => $service->vehicle_id,
                'service_id' => $service->id,
                'recorded_at' => Carbon::now()->subMinutes($index * 7),
                'latitude' => $coord['lat'],
                'longitude' => $coord['lng'],
                'accuracy' => $coord['accuracy'],
                'is_manual' => $coord['manual'],
                'captured_by' => $admin?->id,
            ]);
        }

        // 3. Vehicle-scoped historical rows (no service_id) so the
        //    "Ubicaciones" index has variety + the 24h fallback path is
        //    populated for vehicles without a current service link.
        foreach ($vehicles->take(4) as $index => $vehicle) {
            $coord = $coordinates[$index % count($coordinates)];

            VehicleLocation::create([
                'vehicle_id' => $vehicle->id,
                'recorded_at' => Carbon::now()->subHours(rand(2, 18)),
                'latitude' => $coord['lat'],
                'longitude' => $coord['lng'],
                'accuracy' => $coord['accuracy'],
                'is_manual' => $coord['manual'],
                'captured_by' => $admin?->id,
            ]);
        }
    }
}
