<?php

namespace Database\Seeders;

use App\Models\Fuec;
use App\Models\Service;
use Illuminate\Database\Seeder;

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

        foreach ($closedServices as $index => $service) {
            Fuec::firstOrCreate(
                ['service_id' => $service->id],
                [
                    'service_id' => $service->id,
                    'consecutive_number' => 1000 + $index + 1,
                    'generated_at' => $service->service_date->format('Y-m-d').' 18:00:00',
                    'qr_code' => 'FUEC-'.str_pad($index + 1, 5, '0', STR_PAD_LEFT),
                    'status' => 'active',
                    'pdf_url' => null,
                ],
            );
        }
    }
}
