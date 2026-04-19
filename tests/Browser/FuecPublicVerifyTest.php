<?php

use App\Enums\FuecStatus;
use App\Models\Contract;
use App\Models\Driver;
use App\Models\Fuec;
use App\Models\FuecNumberRange;
use App\Models\Service;
use App\Models\ThirdParty;
use App\Models\User;
use App\Models\Vehicle;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function (): void {
    $this->artisan('migrate:fresh', ['--no-interaction' => true]);
    config()->set('sgte.fuec_enabled', true);
});

function fuecVerifyAdminUser(): User
{
    $role = SpatieRole::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $user = User::factory()->create([
        'email' => env('SUPER_ADMIN_USER') ?: 'super-admin@sgte.app',
        'password' => bcrypt(env('SUPER_ADMIN_PASSWORD') ?: 'password'),
    ]);
    $user->assignRole($role);

    return $user;
}

function fuecVerifySeedActive(): Fuec
{
    $customer = ThirdParty::factory()->create([
        'is_customer' => true,
        'is_natural_person' => false,
        'company_name' => 'Cliente Verify Dusk S.A.',
    ]);
    $contract = Contract::factory()->create([
        'third_party_id' => $customer->id,
        'contract_number' => 'CT-VERIFY-001',
    ]);
    $vehicle = Vehicle::factory()->create(['plate' => 'VRF99']);
    $driver = Driver::factory()->create([
        'first_name' => 'Sofía',
        'first_lastname' => 'Pérez',
    ]);
    $service = Service::factory()->create([
        'contract_id' => $contract->id,
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
    ]);
    $range = FuecNumberRange::factory()->active()->create([
        'resolution_number' => 'RES-VERIFY',
        'resolution_year' => 2026,
    ]);

    return Fuec::factory()->create([
        'service_id' => $service->id,
        'fuec_number_range_id' => $range->id,
        'consecutive_number' => 9001,
        'status' => FuecStatus::Active,
    ]);
}

test('public verify page renders VIGENTE for an active FUEC without authentication', function (): void {
    $fuec = fuecVerifySeedActive();

    $this->browse(function (Browser $browser) use ($fuec): void {
        // Dusk's assertSee on accented text is finicky; we anchor on
        // the ASCII substrings + the raw HTML source for diacritic-
        // bearing fields.
        $browser->visit("/fuec/verify/{$fuec->uuid}")
            ->waitForText('VIGENTE')
            ->assertSee('SGTE')
            ->assertSee('9001')
            ->assertSee('RES-VERIFY')
            ->assertSee('CT-VERIFY-001')
            ->assertSee('Cliente Verify Dusk S.A.')
            ->assertSee('VRF99')
            ->assertSourceHas('Sof')
            ->assertDontSee('ANULADO')
            ->screenshot('fuec-verify-vigente');
    });
});

test('public verify page renders ANULADO after the FUEC is cancelled', function (): void {
    $fuec = fuecVerifySeedActive();
    $fuec->update(['status' => FuecStatus::Cancelled]);

    $this->browse(function (Browser $browser) use ($fuec): void {
        $browser->visit("/fuec/verify/{$fuec->uuid}")
            ->waitForText('ANULADO')
            ->assertSourceHas('Documento Anulado')
            ->assertSourceHas('no es v')
            ->assertDontSee('VIGENTE')
            ->screenshot('fuec-verify-anulado');
    });
});

test('public verify page 404s for an unknown UUID', function (): void {
    $this->browse(function (Browser $browser): void {
        $browser->visit('/fuec/verify/deadbeef-dead-beef-dead-beefdeadbeef')
            ->assertSourceMissing('VIGENTE')
            ->assertSourceMissing('ANULADO')
            ->screenshot('fuec-verify-404');
    });
});

// Flag-off 404s are covered by FuecVerifyControllerTest::'public verify 404s
// when the module is disabled' (Pest). Replicating here would require toggling
// config at the Dusk HTTP boundary, which isn't supported without restarting
// the whole stack. Kept out of Dusk scope by design.
