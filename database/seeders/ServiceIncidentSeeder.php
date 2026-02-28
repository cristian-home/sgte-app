<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServiceIncident;
use App\Models\User;
use Illuminate\Database\Seeder;

class ServiceIncidentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $services = Service::where('service_status', 'closed')->get();
        $registrar = User::first();

        if ($services->isEmpty() || ! $registrar) {
            return;
        }

        $incidents = [
            [
                'service_index' => 0,
                'incident_type' => 'traffic',
                'description' => 'Congestion vehicular en la Avenida NQS a la altura de la calle 26, retraso de 15 minutos',
                'is_driver_report' => true,
                'reported_at' => '2026-02-24 06:30:00',
                'affects_billing' => false,
                'additional_value' => null,
            ],
            [
                'service_index' => 1,
                'incident_type' => 'delay',
                'description' => 'Inicio de ruta con 5 minutos de retraso por espera de estudiantes en el ultimo punto de recogida',
                'is_driver_report' => true,
                'reported_at' => '2026-02-24 05:50:00',
                'affects_billing' => false,
                'additional_value' => null,
            ],
            [
                'service_index' => 2,
                'incident_type' => 'weather',
                'description' => 'Lluvia fuerte en la via Bogota-Zipaquira, se redujo velocidad por seguridad',
                'is_driver_report' => false,
                'reported_at' => '2026-02-25 09:45:00',
                'affects_billing' => true,
                'additional_value' => 50000.00,
            ],
        ];

        foreach ($incidents as $i) {
            $service = $services[$i['service_index'] % $services->count()];

            ServiceIncident::create([
                'service_id' => $service->id,
                'incident_type' => $i['incident_type'],
                'description' => $i['description'],
                'registrar_id' => $registrar->id,
                'is_driver_report' => $i['is_driver_report'],
                'reported_at' => $i['reported_at'],
                'affects_billing' => $i['affects_billing'],
                'additional_value' => $i['additional_value'],
            ]);
        }
    }
}
