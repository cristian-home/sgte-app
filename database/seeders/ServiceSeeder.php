<?php

namespace Database\Seeders;

use App\Enums\PaymentMethod;
use App\Enums\ServiceStatus;
use App\Enums\VehicleStatus;
use App\Models\Contract;
use App\Models\Driver;
use App\Models\Invoice;
use App\Models\Municipality;
use App\Models\Service;
use App\Models\Vehicle;
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

        $bogota = Municipality::where('code', '11001')->first();
        $zipaquira = Municipality::where('code', '25899')->first();

        $services = [
            [
                'contract_index' => 0,
                'vehicle_index' => 0,
                'driver_index' => 0,
                'invoice_index' => 0,
                'service_date' => '2026-02-24',
                'origin_municipality_id' => $bogota?->id,
                'origin_address' => 'Barrio Kennedy',
                'destination_municipality_id' => $bogota?->id,
                'destination_address' => 'Clinica San Rafael - Calle 17',
                'planned_start_time' => '06:00',
                'planned_duration' => 60,
                'actual_start_time' => '06:05',
                'actual_end_time' => '07:00',
                'unit_value' => 150000.00,
                'quantity' => 1,
                'billing_group' => 'Salud',
                'payment_method' => PaymentMethod::Credit->value,
                'service_status' => ServiceStatus::Closed->value,
            ],
            [
                'contract_index' => 1,
                'vehicle_index' => 1,
                'driver_index' => 1,
                'invoice_index' => 1,
                'service_date' => '2026-02-24',
                'origin_municipality_id' => $bogota?->id,
                'origin_address' => 'Calle 170',
                'destination_municipality_id' => $bogota?->id,
                'destination_address' => 'Colegio del Rosario - Carrera 7',
                'planned_start_time' => '05:30',
                'planned_duration' => 90,
                'actual_start_time' => '05:35',
                'actual_end_time' => '07:00',
                'unit_value' => 200000.00,
                'quantity' => 1,
                'billing_group' => 'Escolar',
                'payment_method' => PaymentMethod::Credit->value,
                'service_status' => ServiceStatus::Closed->value,
            ],
            [
                'contract_index' => 2,
                'vehicle_index' => 2,
                'driver_index' => 2,
                'invoice_index' => 2,
                'service_date' => '2026-02-25',
                'origin_municipality_id' => $bogota?->id,
                'origin_address' => 'Hotel Dann Carlton - Av 19',
                'destination_municipality_id' => $zipaquira?->id,
                'destination_address' => 'Catedral de Sal',
                'planned_start_time' => '08:00',
                'planned_duration' => 240,
                'actual_start_time' => '08:10',
                'actual_end_time' => '12:15',
                'unit_value' => 450000.00,
                'quantity' => 1,
                'billing_group' => 'Turismo',
                'payment_method' => PaymentMethod::Transfer->value,
                'service_status' => ServiceStatus::Closed->value,
            ],
            [
                'contract_index' => 3,
                'vehicle_index' => 3,
                'driver_index' => 3,
                'invoice_index' => null,
                'service_date' => '2026-02-27',
                'origin_municipality_id' => $bogota?->id,
                'origin_address' => 'Aeropuerto El Dorado',
                'destination_municipality_id' => $bogota?->id,
                'destination_address' => 'Centro Empresarial Salitre',
                'planned_start_time' => '14:00',
                'planned_duration' => 45,
                'actual_start_time' => null,
                'actual_end_time' => null,
                'unit_value' => 120000.00,
                'quantity' => 1,
                'billing_group' => null,
                'payment_method' => PaymentMethod::Cash->value,
                'service_status' => ServiceStatus::Open->value,
            ],
            [
                'contract_index' => 0,
                'vehicle_index' => 0,
                'driver_index' => 4,
                'invoice_index' => null,
                'service_date' => '2026-02-28',
                'origin_municipality_id' => $bogota?->id,
                'origin_address' => 'Suba',
                'destination_municipality_id' => $bogota?->id,
                'destination_address' => 'Clinica San Rafael - Calle 17',
                'planned_start_time' => '07:00',
                'planned_duration' => 75,
                'actual_start_time' => null,
                'actual_end_time' => null,
                'unit_value' => 160000.00,
                'quantity' => 2,
                'billing_group' => 'Salud',
                'payment_method' => PaymentMethod::Credit->value,
                'service_status' => ServiceStatus::Open->value,
            ],
        ];

        foreach ($services as $s) {
            $contract = $contracts[$s['contract_index'] % $contracts->count()];
            $vehicle = $vehicles[$s['vehicle_index'] % $vehicles->count()];
            $driver = $drivers[$s['driver_index'] % $drivers->count()];
            $invoice = $s['invoice_index'] !== null ? $invoices[$s['invoice_index'] % $invoices->count()] : null;

            Service::create([
                'contract_id' => $contract->id,
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'invoice_id' => $invoice?->id,
                'service_date' => $s['service_date'],
                'origin_municipality_id' => $s['origin_municipality_id'],
                'origin_address' => $s['origin_address'],
                'destination_municipality_id' => $s['destination_municipality_id'],
                'destination_address' => $s['destination_address'],
                'planned_start_time' => $s['planned_start_time'],
                'planned_duration' => $s['planned_duration'],
                'actual_start_time' => $s['actual_start_time'],
                'actual_end_time' => $s['actual_end_time'],
                'unit_value' => $s['unit_value'],
                'quantity' => $s['quantity'],
                'billing_group' => $s['billing_group'],
                'payment_method' => $s['payment_method'],
                'service_status' => $s['service_status'],
            ]);
        }

        Service::factory()->count(50)->create();
    }
}
