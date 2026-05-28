<?php

namespace Database\Seeders;

use App\Enums\ContractObject;
use App\Models\Contract;
use App\Models\ThirdParty;
use App\Support\Tz;
use Database\Seeders\Support\SeedClock;
use Illuminate\Database\Seeder;

class ContractSeeder extends Seeder
{
    public function run(): void
    {
        $customers = ThirdParty::where('is_customer', true)->get();

        // Defensive guard: in environments where the initialization
        // migration is skipped (notably `testing`) there are no
        // pre-seeded third parties, and the previous index-modulo math
        // crashed with "Division by zero". Idempotent early return
        // matches the rest of the seeder family.
        if ($customers->isEmpty()) {
            return;
        }

        $tz = Tz::operation();
        $year = SeedClock::today()->format('Y');

        // All offsets are relative to today so the seeded contract set
        // moves coherently with the system clock: ~6 months ago opens to
        // ~18 months from now (active), plus one already-expired contract
        // that finished a year ago.
        $contractData = [
            [
                'contract_number' => "CT-0001-{$year}",
                'contract_object' => ContractObject::Health->value,
                'start_offset' => -180,
                'end_offset' => 540,
                'route_description' => 'Transporte de pacientes desde sus hogares hasta la Clinica San Rafael y regreso',
                'is_generic' => false,
                'active' => true,
            ],
            [
                'contract_number' => "CT-0002-{$year}",
                'contract_object' => ContractObject::Business->value,
                'start_offset' => -120,
                'end_offset' => 245,
                'route_description' => 'Transporte escolar ruta norte y ruta sur del Colegio del Rosario',
                'is_generic' => false,
                'active' => true,
            ],
            [
                'contract_number' => "CT-0003-{$year}",
                'contract_object' => ContractObject::Tourism->value,
                'start_offset' => -150,
                'end_offset' => 215,
                'route_description' => 'Traslado de huespedes del Hotel Dann Carlton a destinos turisticos en Bogota',
                'is_generic' => false,
                'active' => true,
            ],
            [
                'contract_number' => "CT-0004-{$year}",
                'contract_object' => ContractObject::Occasional->value,
                'start_offset' => -90,
                'end_offset' => 275,
                'route_description' => 'Servicios ocasionales de transporte especial bajo demanda',
                'is_generic' => true,
                'active' => true,
            ],
            [
                'contract_number' => 'CT-0005-PREV',
                'contract_object' => ContractObject::Health->value,
                'start_offset' => -550,
                'end_offset' => -365,
                'route_description' => 'Contrato anterior de transporte de pacientes - finalizado',
                'is_generic' => false,
                'active' => false,
            ],
        ];

        foreach ($contractData as $index => $data) {
            $customer = $customers[$index % $customers->count()];

            $startDate = SeedClock::dateString($data['start_offset']);
            $endDate = SeedClock::dateString($data['end_offset']);
            unset($data['start_offset'], $data['end_offset']);

            Contract::firstOrCreate(
                ['contract_number' => $data['contract_number']],
                array_merge($data, [
                    'third_party_id' => $customer->id,
                    'timezone' => $tz,
                    'start_at' => Tz::startOfDayInTzAsUtc($startDate, $tz),
                    'end_at' => Tz::endOfDayInTzAsUtc($endDate, $tz),
                ]),
            );
        }
    }
}
