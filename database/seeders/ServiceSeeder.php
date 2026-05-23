<?php

namespace Database\Seeders;

use App\Enums\BillingGroup;
use App\Enums\PaymentMethod;
use App\Enums\ServiceStatus;
use App\Enums\VehicleStatus;
use App\Models\Contract;
use App\Models\Driver;
use App\Models\Invoice;
use App\Models\Municipality;
use App\Models\Service;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Database\Factories\Support\RealColombianAddresses;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Service::query()->exists()) {
            return;
        }

        $contracts = Contract::where('active', true)->get();
        $vehicles = Vehicle::where('status', VehicleStatus::Active)->get();
        $drivers = Driver::where('active', true)->get();
        $invoices = Invoice::all();

        // Defensive guard: in environments where the initialization
        // migration is skipped (notably `testing`) the catalog/master-
        // data fixtures don't exist, so the modulo distribution below
        // would crash with "Division by zero". Idempotent early return
        // matches the rest of the seeder family (ContractSeeder,
        // ServiceIncidentSeeder).
        if ($contracts->isEmpty() || $vehicles->isEmpty() || $drivers->isEmpty()) {
            return;
        }

        // Resolve municipality_id by DANE code from the curated landmark
        // list, so demo origins/destinations always carry a real
        // (address, coordinates, source, accuracy, place_id) tuple —
        // same shape the address autocomplete and pin picker would
        // produce in production.
        $byCode = Municipality::whereIn('code', ['11001', '25899', '25754', '05001'])
            ->pluck('id', 'code');

        $landmarks = collect(RealColombianAddresses::all())->keyBy(
            fn (array $row) => "{$row['municipality_code']}::{$row['address']}",
        );

        $pickLandmark = function (string $key) use ($landmarks): array {
            $row = $landmarks->get($key);
            if ($row === null) {
                throw new \RuntimeException("Landmark not found in fixture: {$key}");
            }

            return $row;
        };

        $services = [
            [
                'contract_index' => 0,
                'vehicle_index' => 0,
                'driver_index' => 0,
                'invoice_index' => 0,
                'service_date' => '2026-02-24',
                'origin' => $pickLandmark('11001::Calle 170 #54-90'),
                'destination' => $pickLandmark('11001::Carrera 7 #40-62'),
                'planned_start_time' => '06:00',
                'planned_duration' => 60,
                'actual_start_time' => '06:05',
                'actual_end_time' => '07:00',
                'unit_value' => 150000.00,
                'quantity' => 1,
                'billing_groups' => [BillingGroup::Salud->value],
                'payment_method' => PaymentMethod::Credit->value,
                'service_status' => ServiceStatus::Closed->value,
            ],
            [
                'contract_index' => 1,
                'vehicle_index' => 1,
                'driver_index' => 1,
                'invoice_index' => 1,
                'service_date' => '2026-02-24',
                'origin' => $pickLandmark('11001::Calle 100 #11A-35'),
                'destination' => $pickLandmark('11001::Carrera 13 #93-40'),
                'planned_start_time' => '05:30',
                'planned_duration' => 90,
                'actual_start_time' => '05:35',
                'actual_end_time' => '07:00',
                'unit_value' => 200000.00,
                'quantity' => 1,
                'billing_groups' => [BillingGroup::Escolar->value],
                'payment_method' => PaymentMethod::Credit->value,
                'service_status' => ServiceStatus::Closed->value,
            ],
            [
                'contract_index' => 2,
                'vehicle_index' => 2,
                'driver_index' => 2,
                'invoice_index' => 2,
                'service_date' => '2026-02-25',
                'origin' => $pickLandmark('11001::Carrera 11 #82-71'),
                'destination' => $pickLandmark('25899::Calle 1 #6-14'),
                'planned_start_time' => '08:00',
                'planned_duration' => 240,
                'actual_start_time' => '08:10',
                'actual_end_time' => '12:15',
                'unit_value' => 450000.00,
                'quantity' => 1,
                'billing_groups' => [BillingGroup::Turismo->value],
                'payment_method' => PaymentMethod::Transfer->value,
                'service_status' => ServiceStatus::Closed->value,
            ],
            [
                'contract_index' => 3,
                'vehicle_index' => 3,
                'driver_index' => 3,
                'invoice_index' => null,
                'service_date' => '2026-02-27',
                'origin' => $pickLandmark('11001::Calle 26 #103-09'),
                'destination' => $pickLandmark('11001::Avenida Calle 26 #57-83'),
                'planned_start_time' => '14:00',
                'planned_duration' => 45,
                'actual_start_time' => null,
                'actual_end_time' => null,
                'unit_value' => 120000.00,
                'quantity' => 1,
                'billing_groups' => null,
                'payment_method' => PaymentMethod::Cash->value,
                'service_status' => ServiceStatus::Open->value,
            ],
            [
                'contract_index' => 0,
                'vehicle_index' => 0,
                'driver_index' => 4,
                'invoice_index' => null,
                'service_date' => '2026-02-28',
                'origin' => $pickLandmark('11001::Calle 41A Sur #83-17'),
                'destination' => $pickLandmark('11001::Carrera 7 #40-62'),
                'planned_start_time' => '07:00',
                'planned_duration' => 75,
                'actual_start_time' => null,
                'actual_end_time' => null,
                'unit_value' => 160000.00,
                'quantity' => 2,
                'billing_groups' => [BillingGroup::Salud->value, BillingGroup::Empresarial->value],
                'payment_method' => PaymentMethod::Credit->value,
                'service_status' => ServiceStatus::Open->value,
            ],
        ];

        $tz = (string) config('app.operation_tz', 'America/Bogota');

        foreach ($services as $s) {
            $contract = $contracts[$s['contract_index'] % $contracts->count()];
            $vehicle = $vehicles[$s['vehicle_index'] % $vehicles->count()];
            $driver = $drivers[$s['driver_index'] % $drivers->count()];
            $invoice = $s['invoice_index'] !== null ? $invoices[$s['invoice_index'] % $invoices->count()] : null;

            $plannedAt = CarbonImmutable::createFromFormat('Y-m-d H:i', "{$s['service_date']} {$s['planned_start_time']}", $tz)->utc();
            $actualStart = $s['actual_start_time']
                ? CarbonImmutable::createFromFormat('Y-m-d H:i', "{$s['service_date']} {$s['actual_start_time']}", $tz)->utc()
                : null;
            $actualEnd = $s['actual_end_time']
                ? CarbonImmutable::createFromFormat('Y-m-d H:i', "{$s['service_date']} {$s['actual_end_time']}", $tz)->utc()
                : null;

            Service::create([
                'contract_id' => $contract->id,
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'invoice_id' => $invoice?->id,
                'service_date_local' => $s['service_date'],
                'origin_municipality_id' => $byCode->get($s['origin']['municipality_code']),
                'origin_address' => $s['origin']['address'],
                'origin_coordinates' => $s['origin']['coordinates'],
                'origin_coordinates_source' => $s['origin']['source'],
                'origin_coordinates_accuracy' => $s['origin']['accuracy'],
                'origin_place_id' => $s['origin']['place_id'] ?? null,
                'destination_municipality_id' => $byCode->get($s['destination']['municipality_code']),
                'destination_address' => $s['destination']['address'],
                'destination_coordinates' => $s['destination']['coordinates'],
                'destination_coordinates_source' => $s['destination']['source'],
                'destination_coordinates_accuracy' => $s['destination']['accuracy'],
                'destination_place_id' => $s['destination']['place_id'] ?? null,
                'planned_start_at' => $plannedAt,
                'planned_duration' => $s['planned_duration'],
                'actual_start_at' => $actualStart,
                'actual_end_at' => $actualEnd,
                'timezone' => $tz,
                'unit_value' => $s['unit_value'],
                'quantity' => $s['quantity'],
                'billing_groups' => $s['billing_groups'],
                'payment_method' => $s['payment_method'],
                'service_status' => $s['service_status'],
            ]);
        }

        Service::factory()->count(50)->create();
    }
}
