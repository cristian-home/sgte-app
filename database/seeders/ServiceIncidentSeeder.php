<?php

namespace Database\Seeders;

use App\Models\IncidentType;
use App\Models\Service;
use App\Models\ServiceIncident;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\Support\SeedClock;
use Illuminate\Database\Seeder;

class ServiceIncidentSeeder extends Seeder
{
    /**
     * Three deterministic incidents tied to specific curated services
     * via (day_offset, index-of-day) lookup. Lookups stay stable across
     * runs because ServiceSeeder always inserts in the same order, so
     * the same (day, slot) → same service.
     */
    public function run(): void
    {
        if (ServiceIncident::query()->exists()) {
            return;
        }

        $registrar = User::query()->where('email', 'admin@sgte.app')->first()
            ?? User::first();

        if (! $registrar) {
            return;
        }

        $incidents = [
            [
                'day_offset' => -5,
                'index_of_day' => 0,
                'incident_type_code' => 'TRAFFIC',
                'description' => 'Congestion vehicular en la Avenida NQS a la altura de la calle 26, retraso de 15 minutos',
                'is_driver_report' => true,
                'reported_offset_minutes' => 30,
                'affects_billing' => false,
                'additional_value' => null,
            ],
            [
                'day_offset' => -3,
                'index_of_day' => 1,
                'incident_type_code' => 'DELAY',
                'description' => 'Inicio de ruta con retraso por espera de pasajeros en el ultimo punto de recogida',
                'is_driver_report' => true,
                'reported_offset_minutes' => -10,
                'affects_billing' => false,
                'additional_value' => null,
            ],
            [
                'day_offset' => -1,
                'index_of_day' => 0,
                'incident_type_code' => 'WEATHER',
                'description' => 'Lluvia fuerte en la via, se redujo velocidad por seguridad',
                'is_driver_report' => false,
                'reported_offset_minutes' => 45,
                'affects_billing' => true,
                'additional_value' => 50000.00,
            ],
        ];

        foreach ($incidents as $i) {
            $service = $this->serviceFor($i['day_offset'], $i['index_of_day']);
            $incidentType = IncidentType::query()->where('code', $i['incident_type_code'])->first();

            if ($service === null || $incidentType === null) {
                continue;
            }

            $reportedAt = CarbonImmutable::instance($service->planned_start_at)
                ->addMinutes($i['reported_offset_minutes']);

            ServiceIncident::create([
                'service_id' => $service->id,
                'incident_type_id' => $incidentType->id,
                'description' => $i['description'],
                'registrar_id' => $registrar->id,
                'is_driver_report' => $i['is_driver_report'],
                'reported_at' => $reportedAt,
                'affects_billing' => $i['affects_billing'],
                'additional_value' => $i['additional_value'],
            ]);
        }
    }

    /**
     * Resolve the curated service at (today + $dayOffset, slot $idx).
     * Slots are ordered by planned_start_at, matching the dataset order
     * inside ServiceSeeder.
     */
    private function serviceFor(int $dayOffset, int $idx): ?Service
    {
        return Service::query()
            ->whereDate('service_date_local', SeedClock::dateString($dayOffset))
            ->orderBy('planned_start_at')
            ->skip($idx)
            ->first();
    }
}
