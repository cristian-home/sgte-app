<?php

namespace Database\Seeders;

use App\Enums\ContractObject;
use App\Models\Contract;
use App\Models\ThirdParty;
use Illuminate\Database\Seeder;

class ContractSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
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

        $contractData = [
            [
                'contract_number' => 'CT-0001-2026',
                'contract_object' => ContractObject::Health->value,
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
                'route_description' => 'Transporte de pacientes desde sus hogares hasta la Clinica San Rafael y regreso',
                'is_generic' => false,
                'active' => true,
            ],
            [
                'contract_number' => 'CT-0002-2026',
                'contract_object' => ContractObject::Business->value,
                'start_date' => '2026-02-01',
                'end_date' => '2027-01-31',
                'route_description' => 'Transporte escolar ruta norte y ruta sur del Colegio del Rosario',
                'is_generic' => false,
                'active' => true,
            ],
            [
                'contract_number' => 'CT-0003-2026',
                'contract_object' => ContractObject::Tourism->value,
                'start_date' => '2026-01-15',
                'end_date' => '2026-12-15',
                'route_description' => 'Traslado de huespedes del Hotel Dann Carlton a destinos turisticos en Bogota',
                'is_generic' => false,
                'active' => true,
            ],
            [
                'contract_number' => 'CT-0004-2026',
                'contract_object' => ContractObject::Occasional->value,
                'start_date' => '2026-03-01',
                'end_date' => '2027-02-28',
                'route_description' => 'Servicios ocasionales de transporte especial bajo demanda',
                'is_generic' => true,
                'active' => true,
            ],
            [
                'contract_number' => 'CT-0005-2025',
                'contract_object' => ContractObject::Health->value,
                'start_date' => '2025-06-01',
                'end_date' => '2025-12-31',
                'route_description' => 'Contrato anterior de transporte de pacientes - finalizado',
                'is_generic' => false,
                'active' => false,
            ],
        ];

        foreach ($contractData as $index => $data) {
            $customer = $customers[$index % $customers->count()];

            Contract::firstOrCreate(
                ['contract_number' => $data['contract_number']],
                array_merge($data, ['third_party_id' => $customer->id]),
            );
        }
    }
}
