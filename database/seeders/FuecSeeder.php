<?php

namespace Database\Seeders;

use App\Models\Fuec;
use App\Models\FuecNumberRange;
use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FuecSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $closedServices = Service::where('service_status', 'closed')->get();

        if ($closedServices->isEmpty()) {
            return;
        }

        $range = FuecNumberRange::firstOrCreate(
            ['resolution_number' => 'RES-0001', 'resolution_year' => (int) now()->format('Y')],
            [
                'range_from' => 1000,
                'range_to' => 9999,
                'active' => true,
                'notes' => 'Rango inicial de demostración para el entorno de staging.',
            ],
        );

        foreach ($closedServices as $index => $service) {
            Fuec::firstOrCreate(
                ['service_id' => $service->id],
                [
                    'uuid' => (string) Str::uuid(),
                    'service_id' => $service->id,
                    'fuec_number_range_id' => $range->id,
                    'consecutive_number' => $range->range_from + $index,
                    'generated_at' => $service->service_date->format('Y-m-d').' 18:00:00',
                    'qr_code' => (string) Str::uuid(),
                    'status' => 'active',
                    'pdf_path' => null,
                    'pdf_disk' => 's3',
                ],
            );
        }
    }
}
