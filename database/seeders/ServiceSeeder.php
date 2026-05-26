<?php

namespace Database\Seeders;

use App\Enums\BillingGroup;
use App\Enums\PaymentMethod;
use App\Enums\ServiceStatus;
use App\Enums\VehicleStatus;
use App\Jobs\FetchServiceRoute;
use App\Models\Contract;
use App\Models\Driver;
use App\Models\Invoice;
use App\Models\Municipality;
use App\Models\Service;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Database\Factories\Support\RealColombianAddresses;
use Database\Seeders\Support\SeedClock;
use Illuminate\Database\Seeder;
use Throwable;

/**
 * Curated, time-anchored service dataset.
 *
 * Layout (~32 services across ±7 days around today):
 *   - 7 past days × 2 = 14 Closed services (4 days carry invoices)
 *   -            today: 4 services covering the full day so the
 *                       in-progress / future-today / already-closed
 *                       mix appears regardless of seeding hour
 *   - 7 future days × 2 = 14 Open scheduled services
 *
 * For each service:
 *   - `planned_duration` is derived from Google Routes (when reachable)
 *     and falls back to a haversine + 30 km/h urban average — never
 *     invented at random.
 *   - `service_status` / `actual_start_at` / `actual_end_at` are derived
 *     from `planned_start_at` vs. now, so the dataset stays internally
 *     consistent regardless of when the seeder runs.
 *   - Vehicles, drivers and contracts are assigned by fixed index across
 *     the 5 curated rows seeded by the initialization migration.
 */
class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        if (Service::query()->exists()) {
            return;
        }

        $contracts = Contract::where('active', true)->get();
        $vehicles = Vehicle::where('status', VehicleStatus::Active)->get();
        $drivers = Driver::where('active', true)->get();
        $invoices = Invoice::orderBy('id')->get();

        if ($contracts->isEmpty() || $vehicles->isEmpty() || $drivers->isEmpty()) {
            return;
        }

        $landmarks = $this->landmarks();

        $now = CarbonImmutable::now('UTC');

        $invoiceCursor = 0;

        foreach ($this->dataset() as $row) {
            $contract = $contracts[$row['cidx'] % $contracts->count()];
            $vehicle = $vehicles[$row['vidx'] % $vehicles->count()];
            $driver = $drivers[$row['didx'] % $drivers->count()];

            $origin = $landmarks[$row['origin']];
            $destination = $landmarks[$row['dest']];

            $plannedStart = SeedClock::at($row['off'], $row['start']);

            // Invoice assignment: only Closed services 3+ days in the past
            // get one, round-robin across the curated invoice list. Keeps
            // a believable mix of invoiced vs. pending-to-bill services.
            $invoice = null;
            if ($row['off'] <= -3 && $invoices->isNotEmpty()) {
                $invoice = $invoices[$invoiceCursor % $invoices->count()];
                $invoiceCursor++;
            }

            $service = Service::create([
                'contract_id' => $contract->id,
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'invoice_id' => $invoice?->id,
                'service_date_local' => SeedClock::dateString($row['off']),
                'origin_municipality_id' => $this->municipalityId($origin['municipality_code']),
                'origin_address' => $origin['address'],
                'origin_coordinates' => $origin['coordinates'],
                'origin_coordinates_source' => $origin['source'],
                'origin_coordinates_accuracy' => $origin['accuracy'],
                'origin_place_id' => $origin['place_id'] ?? null,
                'destination_municipality_id' => $this->municipalityId($destination['municipality_code']),
                'destination_address' => $destination['address'],
                'destination_coordinates' => $destination['coordinates'],
                'destination_coordinates_source' => $destination['source'],
                'destination_coordinates_accuracy' => $destination['accuracy'],
                'destination_place_id' => $destination['place_id'] ?? null,
                'planned_start_at' => $plannedStart,
                'planned_duration' => 60, // placeholder, recomputed below
                'timezone' => SeedClock::tz(),
                'unit_value' => $row['unit'],
                'quantity' => $row['qty'],
                'billing_groups' => $row['bg'],
                'payment_method' => $row['pm']->value,
                'service_status' => ServiceStatus::Open->value, // placeholder
            ]);

            // Inline route fetch so `route_duration_s` is available for
            // the duration derivation below. Mirrors the pattern in
            // VehicleLocationSeeder: degrades gracefully (haversine
            // fallback) when Google is unavailable.
            try {
                FetchServiceRoute::dispatchSync($service);
                $service->refresh();
            } catch (Throwable $e) {
                report($e);
            }

            $duration = $this->resolveDuration($service);
            $plannedEnd = $plannedStart->addMinutes($duration);
            [$status, $actualStart, $actualEnd] = $this->deriveLifecycle(
                $row['off'], $plannedStart, $plannedEnd, $duration, $row['idx'], $now,
            );

            $service->update([
                'planned_duration' => $duration,
                'actual_start_at' => $actualStart,
                'actual_end_at' => $actualEnd,
                'service_status' => $status,
            ]);
        }
    }

    /**
     * Lookup table keyed by short identifier → curated landmark record
     * (same shape as the production address-autocomplete payload).
     *
     * @return array<string, array{municipality_code:string,address:string,coordinates:string,source:string,accuracy:string|null,place_id?:string|null}>
     */
    private function landmarks(): array
    {
        $byAddress = collect(RealColombianAddresses::all())
            ->keyBy(fn (array $row) => "{$row['municipality_code']}::{$row['address']}");

        $pick = function (string $key) use ($byAddress): array {
            $row = $byAddress->get($key);
            if ($row === null) {
                throw new \RuntimeException("Landmark not found in fixture: {$key}");
            }

            return $row + ['accuracy' => null, 'place_id' => null];
        };

        return [
            'BOGOTA_NORTE_170' => $pick('11001::Calle 170 #54-90'),
            'BOGOTA_CENTRO_7' => $pick('11001::Carrera 7 #40-62'),
            'BOGOTA_ZONA_T' => $pick('11001::Carrera 11 #82-71'),
            'BOGOTA_AEROPUERTO' => $pick('11001::Calle 26 #103-09'),
            'BOGOTA_NQS' => $pick('11001::Carrera 30 #45-03'),
            'BOGOTA_C100' => $pick('11001::Calle 100 #11A-35'),
            'BOGOTA_ACC26' => $pick('11001::Avenida Calle 26 #57-83'),
            'BOGOTA_C93' => $pick('11001::Carrera 13 #93-40'),
            'BOGOTA_SUR_41A' => $pick('11001::Calle 41A Sur #83-17'),
            'BOGOTA_CENTRO_8' => $pick('11001::Carrera 8 #5-30'),
            'ZIPAQUIRA' => $pick('25899::Calle 1 #6-14'),
            'SOACHA' => $pick('25754::Carrera 8 #26-60'),
            'CHIA' => $pick('25175::Calle 17 #11-90'),
        ];
    }

    /**
     * The curated dataset itself. Tuned so:
     *   - the 2 services per past day land at distinct hours (early /
     *     afternoon) so the Gantt visualization shows variety
     *   - days -5 / -3 / -1 carry the long routes (intermunicipal)
     *     where ServiceIncidentSeeder attaches its 3 incidents
     *   - today's 4 services span 04:00 / 09:00 / 15:00 / 22:00 so the
     *     derived-from-now lifecycle covers Closed + InProgress +
     *     Open-future regardless of the seeding hour
     *   - vehicle / driver / contract indexes cycle so every row from
     *     the 5-deep curated pool gets exercised
     *
     * @return list<array{off:int,idx:int,cidx:int,vidx:int,didx:int,origin:string,dest:string,start:string,unit:float,qty:int,bg:list<string>,pm:PaymentMethod}>
     */
    private function dataset(): array
    {
        return [
            // —— Pasados (Closed, derivados) ——
            ['off' => -7, 'idx' => 0, 'cidx' => 0, 'vidx' => 0, 'didx' => 0, 'origin' => 'BOGOTA_NORTE_170', 'dest' => 'BOGOTA_CENTRO_7', 'start' => '06:00', 'unit' => 150000.00, 'qty' => 1, 'bg' => [BillingGroup::Salud->value], 'pm' => PaymentMethod::Credit],
            ['off' => -7, 'idx' => 1, 'cidx' => 1, 'vidx' => 1, 'didx' => 1, 'origin' => 'BOGOTA_ACC26', 'dest' => 'BOGOTA_C100', 'start' => '14:30', 'unit' => 180000.00, 'qty' => 1, 'bg' => [BillingGroup::Escolar->value], 'pm' => PaymentMethod::Credit],

            ['off' => -6, 'idx' => 0, 'cidx' => 1, 'vidx' => 2, 'didx' => 2, 'origin' => 'BOGOTA_C100', 'dest' => 'BOGOTA_C93', 'start' => '05:30', 'unit' => 170000.00, 'qty' => 1, 'bg' => [BillingGroup::Escolar->value], 'pm' => PaymentMethod::Credit],
            ['off' => -6, 'idx' => 1, 'cidx' => 2, 'vidx' => 3, 'didx' => 3, 'origin' => 'BOGOTA_ZONA_T', 'dest' => 'BOGOTA_AEROPUERTO', 'start' => '16:00', 'unit' => 200000.00, 'qty' => 1, 'bg' => [BillingGroup::Turismo->value], 'pm' => PaymentMethod::Cash],

            // day -5: idx 0 carries the TRAFFIC incident
            ['off' => -5, 'idx' => 0, 'cidx' => 3, 'vidx' => 4, 'didx' => 4, 'origin' => 'BOGOTA_NQS', 'dest' => 'BOGOTA_ACC26', 'start' => '06:30', 'unit' => 130000.00, 'qty' => 1, 'bg' => [BillingGroup::Empresarial->value], 'pm' => PaymentMethod::Transfer],
            ['off' => -5, 'idx' => 1, 'cidx' => 0, 'vidx' => 0, 'didx' => 0, 'origin' => 'BOGOTA_SUR_41A', 'dest' => 'BOGOTA_CENTRO_8', 'start' => '14:30', 'unit' => 140000.00, 'qty' => 1, 'bg' => [BillingGroup::Salud->value], 'pm' => PaymentMethod::Credit],

            ['off' => -4, 'idx' => 0, 'cidx' => 1, 'vidx' => 1, 'didx' => 1, 'origin' => 'BOGOTA_CENTRO_8', 'dest' => 'BOGOTA_CENTRO_7', 'start' => '07:00', 'unit' => 120000.00, 'qty' => 1, 'bg' => [BillingGroup::Escolar->value], 'pm' => PaymentMethod::Credit],
            ['off' => -4, 'idx' => 1, 'cidx' => 2, 'vidx' => 2, 'didx' => 2, 'origin' => 'BOGOTA_AEROPUERTO', 'dest' => 'BOGOTA_ZONA_T', 'start' => '13:00', 'unit' => 210000.00, 'qty' => 1, 'bg' => [BillingGroup::Turismo->value], 'pm' => PaymentMethod::Cash],

            // day -3: idx 1 carries the DELAY incident (long Zipaquirá route)
            ['off' => -3, 'idx' => 0, 'cidx' => 0, 'vidx' => 3, 'didx' => 3, 'origin' => 'BOGOTA_NORTE_170', 'dest' => 'BOGOTA_C100', 'start' => '06:00', 'unit' => 150000.00, 'qty' => 1, 'bg' => [BillingGroup::Salud->value], 'pm' => PaymentMethod::Credit],
            ['off' => -3, 'idx' => 1, 'cidx' => 2, 'vidx' => 4, 'didx' => 4, 'origin' => 'BOGOTA_CENTRO_7', 'dest' => 'ZIPAQUIRA', 'start' => '11:00', 'unit' => 450000.00, 'qty' => 1, 'bg' => [BillingGroup::Turismo->value], 'pm' => PaymentMethod::Transfer],

            ['off' => -2, 'idx' => 0, 'cidx' => 1, 'vidx' => 0, 'didx' => 0, 'origin' => 'BOGOTA_CENTRO_7', 'dest' => 'SOACHA', 'start' => '08:00', 'unit' => 220000.00, 'qty' => 1, 'bg' => [BillingGroup::Empresarial->value], 'pm' => PaymentMethod::Transfer],
            ['off' => -2, 'idx' => 1, 'cidx' => 3, 'vidx' => 1, 'didx' => 1, 'origin' => 'BOGOTA_ZONA_T', 'dest' => 'BOGOTA_C93', 'start' => '15:00', 'unit' => 110000.00, 'qty' => 1, 'bg' => [BillingGroup::Empresarial->value], 'pm' => PaymentMethod::Cash],

            // day -1: idx 0 carries the WEATHER incident
            ['off' => -1, 'idx' => 0, 'cidx' => 0, 'vidx' => 2, 'didx' => 2, 'origin' => 'BOGOTA_C100', 'dest' => 'BOGOTA_NQS', 'start' => '06:30', 'unit' => 130000.00, 'qty' => 1, 'bg' => [BillingGroup::Salud->value], 'pm' => PaymentMethod::Credit],
            ['off' => -1, 'idx' => 1, 'cidx' => 1, 'vidx' => 3, 'didx' => 3, 'origin' => 'BOGOTA_NQS', 'dest' => 'BOGOTA_NORTE_170', 'start' => '15:30', 'unit' => 140000.00, 'qty' => 1, 'bg' => [BillingGroup::Escolar->value], 'pm' => PaymentMethod::Credit],

            // —— Hoy: 04:00 / 09:00 / 15:00 / 22:00 ——
            ['off' => 0, 'idx' => 0, 'cidx' => 0, 'vidx' => 0, 'didx' => 0, 'origin' => 'BOGOTA_NORTE_170', 'dest' => 'BOGOTA_AEROPUERTO', 'start' => '04:00', 'unit' => 280000.00, 'qty' => 1, 'bg' => [BillingGroup::Turismo->value], 'pm' => PaymentMethod::Cash],
            ['off' => 0, 'idx' => 1, 'cidx' => 1, 'vidx' => 1, 'didx' => 1, 'origin' => 'BOGOTA_C100', 'dest' => 'BOGOTA_ACC26', 'start' => '09:00', 'unit' => 160000.00, 'qty' => 1, 'bg' => [BillingGroup::Escolar->value], 'pm' => PaymentMethod::Credit],
            ['off' => 0, 'idx' => 2, 'cidx' => 2, 'vidx' => 2, 'didx' => 2, 'origin' => 'BOGOTA_ZONA_T', 'dest' => 'CHIA', 'start' => '15:00', 'unit' => 250000.00, 'qty' => 1, 'bg' => [BillingGroup::Turismo->value], 'pm' => PaymentMethod::Transfer],
            ['off' => 0, 'idx' => 3, 'cidx' => 3, 'vidx' => 3, 'didx' => 3, 'origin' => 'BOGOTA_NQS', 'dest' => 'BOGOTA_CENTRO_7', 'start' => '22:00', 'unit' => 130000.00, 'qty' => 1, 'bg' => [BillingGroup::Empresarial->value], 'pm' => PaymentMethod::Cash],

            // —— Futuros (Open programado) ——
            ['off' => 1, 'idx' => 0, 'cidx' => 0, 'vidx' => 4, 'didx' => 4, 'origin' => 'BOGOTA_CENTRO_7', 'dest' => 'BOGOTA_C100', 'start' => '07:00', 'unit' => 150000.00, 'qty' => 1, 'bg' => [BillingGroup::Salud->value], 'pm' => PaymentMethod::Credit],
            ['off' => 1, 'idx' => 1, 'cidx' => 1, 'vidx' => 0, 'didx' => 0, 'origin' => 'BOGOTA_ACC26', 'dest' => 'BOGOTA_ZONA_T', 'start' => '14:00', 'unit' => 170000.00, 'qty' => 1, 'bg' => [BillingGroup::Escolar->value], 'pm' => PaymentMethod::Credit],

            ['off' => 2, 'idx' => 0, 'cidx' => 2, 'vidx' => 1, 'didx' => 1, 'origin' => 'BOGOTA_CENTRO_7', 'dest' => 'BOGOTA_AEROPUERTO', 'start' => '05:00', 'unit' => 230000.00, 'qty' => 1, 'bg' => [BillingGroup::Turismo->value], 'pm' => PaymentMethod::Transfer],
            ['off' => 2, 'idx' => 1, 'cidx' => 3, 'vidx' => 2, 'didx' => 2, 'origin' => 'BOGOTA_C93', 'dest' => 'BOGOTA_SUR_41A', 'start' => '16:00', 'unit' => 180000.00, 'qty' => 1, 'bg' => [BillingGroup::Empresarial->value], 'pm' => PaymentMethod::Cash],

            ['off' => 3, 'idx' => 0, 'cidx' => 0, 'vidx' => 3, 'didx' => 3, 'origin' => 'BOGOTA_CENTRO_7', 'dest' => 'SOACHA', 'start' => '08:30', 'unit' => 220000.00, 'qty' => 1, 'bg' => [BillingGroup::Empresarial->value], 'pm' => PaymentMethod::Transfer],
            ['off' => 3, 'idx' => 1, 'cidx' => 1, 'vidx' => 4, 'didx' => 4, 'origin' => 'BOGOTA_NORTE_170', 'dest' => 'BOGOTA_ACC26', 'start' => '15:00', 'unit' => 140000.00, 'qty' => 1, 'bg' => [BillingGroup::Escolar->value], 'pm' => PaymentMethod::Credit],

            ['off' => 4, 'idx' => 0, 'cidx' => 2, 'vidx' => 0, 'didx' => 0, 'origin' => 'BOGOTA_AEROPUERTO', 'dest' => 'BOGOTA_ZONA_T', 'start' => '11:00', 'unit' => 200000.00, 'qty' => 1, 'bg' => [BillingGroup::Turismo->value], 'pm' => PaymentMethod::Cash],
            ['off' => 4, 'idx' => 1, 'cidx' => 3, 'vidx' => 1, 'didx' => 1, 'origin' => 'BOGOTA_CENTRO_8', 'dest' => 'BOGOTA_CENTRO_7', 'start' => '13:30', 'unit' => 115000.00, 'qty' => 1, 'bg' => [BillingGroup::Empresarial->value], 'pm' => PaymentMethod::Cash],

            ['off' => 5, 'idx' => 0, 'cidx' => 0, 'vidx' => 2, 'didx' => 2, 'origin' => 'BOGOTA_NORTE_170', 'dest' => 'ZIPAQUIRA', 'start' => '07:00', 'unit' => 480000.00, 'qty' => 1, 'bg' => [BillingGroup::Turismo->value], 'pm' => PaymentMethod::Transfer],
            ['off' => 5, 'idx' => 1, 'cidx' => 2, 'vidx' => 3, 'didx' => 3, 'origin' => 'BOGOTA_C100', 'dest' => 'BOGOTA_SUR_41A', 'start' => '14:00', 'unit' => 160000.00, 'qty' => 1, 'bg' => [BillingGroup::Escolar->value], 'pm' => PaymentMethod::Credit],

            ['off' => 6, 'idx' => 0, 'cidx' => 1, 'vidx' => 4, 'didx' => 4, 'origin' => 'BOGOTA_NQS', 'dest' => 'BOGOTA_C93', 'start' => '06:30', 'unit' => 135000.00, 'qty' => 1, 'bg' => [BillingGroup::Salud->value], 'pm' => PaymentMethod::Credit],
            ['off' => 6, 'idx' => 1, 'cidx' => 3, 'vidx' => 0, 'didx' => 0, 'origin' => 'BOGOTA_ZONA_T', 'dest' => 'BOGOTA_AEROPUERTO', 'start' => '17:00', 'unit' => 220000.00, 'qty' => 1, 'bg' => [BillingGroup::Turismo->value], 'pm' => PaymentMethod::Cash],

            ['off' => 7, 'idx' => 0, 'cidx' => 0, 'vidx' => 1, 'didx' => 1, 'origin' => 'BOGOTA_C100', 'dest' => 'CHIA', 'start' => '08:00', 'unit' => 190000.00, 'qty' => 1, 'bg' => [BillingGroup::Turismo->value], 'pm' => PaymentMethod::Transfer],
            ['off' => 7, 'idx' => 1, 'cidx' => 2, 'vidx' => 2, 'didx' => 2, 'origin' => 'BOGOTA_CENTRO_7', 'dest' => 'BOGOTA_NORTE_170', 'start' => '16:30', 'unit' => 150000.00, 'qty' => 1, 'bg' => [BillingGroup::Salud->value], 'pm' => PaymentMethod::Credit],
        ];
    }

    /**
     * Resolve a Bogotá / Cundinamarca / Antioquia DANE code to its
     * municipality row id. Cached per call.
     */
    private function municipalityId(string $code): ?int
    {
        static $cache = [];

        if (! array_key_exists($code, $cache)) {
            $cache[$code] = Municipality::query()
                ->where('code', $code)
                ->value('id');
        }

        return $cache[$code];
    }

    /**
     * Planned duration in minutes — Google Routes preferred (with a 15%
     * urban traffic buffer, snapped to 5-min grid), haversine fallback
     * when the API is unreachable.
     */
    private function resolveDuration(Service $service): int
    {
        if ($service->route_duration_s) {
            $raw = ($service->route_duration_s / 60) * 1.15;

            return max(15, (int) (ceil($raw / 5) * 5));
        }

        $origin = $this->parseCoords($service->origin_coordinates);
        $dest = $this->parseCoords($service->destination_coordinates);

        if ($origin === null || $dest === null) {
            return 60;
        }

        $km = $this->haversineKm($origin['lat'], $origin['lng'], $dest['lat'], $dest['lng']);
        $raw = ($km / 30.0) * 60.0;

        return max(15, (int) (ceil(max(15.0, $raw) / 5) * 5));
    }

    /**
     * Derive (status, actual_start_at, actual_end_at) from when the
     * service is anchored. Past days always Closed, future days always
     * Open-no-actuals, today flips based on now.
     *
     * @return array{0:string, 1:CarbonImmutable|null, 2:CarbonImmutable|null}
     */
    private function deriveLifecycle(
        int $offset,
        CarbonImmutable $plannedStart,
        CarbonImmutable $plannedEnd,
        int $duration,
        int $idx,
        CarbonImmutable $now,
    ): array {
        $overrun = ($idx % 5) * 3; // 0..12 min, deterministic per row

        if ($offset < 0) {
            return [
                ServiceStatus::Closed->value,
                $plannedStart->addMinutes(5),
                $plannedEnd->addMinutes($overrun),
            ];
        }

        if ($offset > 0) {
            return [ServiceStatus::Open->value, null, null];
        }

        // Today — derive from current time vs. the planned window.
        if ($plannedEnd->lt($now)) {
            return [
                ServiceStatus::Closed->value,
                $plannedStart->addMinutes(5),
                $plannedEnd->addMinutes($overrun),
            ];
        }

        if ($plannedStart->lt($now)) {
            return [
                ServiceStatus::Open->value,
                $plannedStart->addMinutes(5),
                null,
            ];
        }

        return [ServiceStatus::Open->value, null, null];
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $r * asin(min(1.0, sqrt($a)));
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    private function parseCoords(?string $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        $parts = explode(',', $value);
        if (count($parts) !== 2) {
            return null;
        }

        [$lat, $lng] = [trim($parts[0]), trim($parts[1])];

        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return null;
        }

        return ['lat' => (float) $lat, 'lng' => (float) $lng];
    }
}
