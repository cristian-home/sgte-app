<?php

namespace Database\Seeders;

use App\Enums\VehicleStatus;
use App\Enums\VehicleType;
use App\Models\Municipality;
use App\Models\ThirdParty;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class VehicleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $provider = ThirdParty::where('is_provider', true)->first();

        $bogota = Municipality::where('code', '11001')->first();
        $medellin = Municipality::where('code', '5001')->first();
        $bucaramanga = Municipality::where('code', '68001')->first();

        $vehicles = [
            [
                'internal_code' => 'V-001',
                'plate' => 'ABC123',
                'mobile_number' => '3101000001',
                'brand' => 'Chevrolet',
                'line' => 'NKR',
                'model_year' => 2022,
                'type' => VehicleType::Buseta->value,
                'engine_number' => 'CHV2022NKR001',
                'chassis_number' => '9GBNG5CD0N1234567',
                'capacity' => 19,
                'municipality_id' => $bogota?->id,
                'is_third_party' => false,
                'third_party_id' => null,
                'soat_due_date' => '2027-03-15',
                'rtm_due_date' => '2027-06-20',
                'operation_card_due_date' => '2028-01-10',
                'status' => VehicleStatus::Active->value,
            ],
            [
                'internal_code' => 'V-002',
                'plate' => 'DEF456',
                'mobile_number' => '3101000002',
                'brand' => 'Toyota',
                'line' => 'Coaster',
                'model_year' => 2023,
                'type' => VehicleType::Bus->value,
                'engine_number' => 'TYT2023CST002',
                'chassis_number' => 'JTGFB518XJ1234568',
                'capacity' => 30,
                'municipality_id' => $bogota?->id,
                'is_third_party' => false,
                'third_party_id' => null,
                'soat_due_date' => '2027-05-10',
                'rtm_due_date' => '2027-08-15',
                'operation_card_due_date' => '2028-04-20',
                'status' => VehicleStatus::Active->value,
            ],
            [
                'internal_code' => 'V-003',
                'plate' => 'GHI789',
                'mobile_number' => '3101000003',
                'brand' => 'Hyundai',
                'line' => 'County',
                'model_year' => 2021,
                'type' => VehicleType::Buseta->value,
                'engine_number' => 'HYD2021CNT003',
                'chassis_number' => 'KMJHG51HPJU234569',
                'capacity' => 25,
                'municipality_id' => $medellin?->id,
                'is_third_party' => false,
                'third_party_id' => null,
                'soat_due_date' => '2027-01-20',
                'rtm_due_date' => '2027-04-10',
                'operation_card_due_date' => '2027-12-05',
                'status' => VehicleStatus::Active->value,
            ],
            [
                'internal_code' => 'V-004',
                'plate' => 'JKL012',
                'mobile_number' => '3101000004',
                'brand' => 'Mercedes-Benz',
                'line' => 'Sprinter',
                'model_year' => 2024,
                'type' => VehicleType::Van->value,
                'engine_number' => 'MBZ2024SPR004',
                'chassis_number' => 'WDB9066331S234570',
                'capacity' => 15,
                'municipality_id' => $bogota?->id,
                'is_third_party' => false,
                'third_party_id' => null,
                'soat_due_date' => '2027-07-25',
                'rtm_due_date' => '2027-10-30',
                'operation_card_due_date' => '2028-06-15',
                'status' => VehicleStatus::Active->value,
            ],
            [
                'internal_code' => 'V-005',
                'plate' => 'MNO345',
                'mobile_number' => '3101000005',
                'brand' => 'Kia',
                'line' => 'Pregio',
                'model_year' => 2020,
                'type' => VehicleType::Van->value,
                'engine_number' => 'KIA2020PRG005',
                'chassis_number' => 'KNCSD81126K234571',
                'capacity' => 12,
                'municipality_id' => $bucaramanga?->id,
                'is_third_party' => true,
                'third_party_id' => $provider?->id,
                'soat_due_date' => '2026-11-30',
                'rtm_due_date' => '2027-02-28',
                'operation_card_due_date' => '2027-09-15',
                'status' => VehicleStatus::Maintenance->value,
            ],
        ];

        foreach ($vehicles as $vehicle) {
            Vehicle::firstOrCreate(
                ['plate' => $vehicle['plate']],
                $vehicle,
            );
        }
    }
}
