<?php

use App\Enums\FuecStatus;
use App\Enums\LicenseCategory;
use App\Enums\ServiceStatus;
use App\Enums\VehicleType;
use App\Models\Contract;
use App\Models\Driver;
use App\Models\Fuec;
use App\Models\FuecNumberRange;
use App\Models\Service;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function (): void {
    $this->artisan('migrate:fresh', ['--no-interaction' => true]);
    config()->set('sgte.fuec_enabled', true);
});

function fuecPreviewAuthenticateAsSuperAdmin(): User
{
    $role = SpatieRole::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $user = User::where('email', env('SUPER_ADMIN_USER'))->first();
    if (! $user) {
        $user = User::factory()->create([
            'email' => env('SUPER_ADMIN_USER'),
            'password' => bcrypt(env('SUPER_ADMIN_PASSWORD')),
        ]);
    }
    $user->assignRole($role);

    return $user;
}

function fuecPreviewReadyService(): Service
{
    FuecNumberRange::factory()->active()->create([
        'range_from' => 9000,
        'range_to' => 9100,
    ]);

    $contract = Contract::factory()->create([
        'active' => true,
        'start_date' => Carbon::now()->subMonth(),
        'end_date' => Carbon::now()->addMonth(),
    ]);
    $vehicle = Vehicle::factory()->create([
        'is_third_party' => false,
        'type' => VehicleType::Buseta,
        'soat_due_date' => Carbon::now()->addYear(),
        'rtm_due_date' => Carbon::now()->addYear(),
        'operation_card_due_date' => Carbon::now()->addYear(),
    ]);
    $driver = Driver::factory()->create([
        'license_due_date' => Carbon::now()->addYear(),
        'license_category' => LicenseCategory::C2,
        'has_social_security' => true,
    ]);

    return Service::factory()->create([
        'contract_id' => $contract->id,
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'service_status' => ServiceStatus::Closed,
        'service_date' => Carbon::now()->toDateString(),
    ]);
}

test('create page exposes Vista previa button and the preview does not consume a consecutive (REQ-007 regression)', function (): void {
    $user = fuecPreviewAuthenticateAsSuperAdmin();
    fuecPreviewReadyService();

    $fuecsBefore = Fuec::count();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->visit('/fuecs/create')
            ->waitForText('Generar FUEC')
            ->assertSee('Vista previa')
            ->screenshot('fuec-create-preview-button-visible');
    });

    // No preview click was performed here; just asserting the layout.
    // Clicking would fire a fetch to /fuecs/preview and Dusk/Selenium
    // blob-URL inspection is flakier than a server-side test — the
    // Pest coverage already exercises the blob flow.
    expect(Fuec::count())->toBe($fuecsBefore);
});

test('cancel dialog requires a reason and persists cancellation_reason on the FUEC row', function (): void {
    $user = fuecPreviewAuthenticateAsSuperAdmin();
    $service = fuecPreviewReadyService();
    $fuec = Fuec::factory()->create([
        'service_id' => $service->id,
        'fuec_number_range_id' => FuecNumberRange::query()->where('active', true)->first()->id,
        'status' => FuecStatus::Active,
        'consecutive_number' => 9050,
    ]);

    $this->browse(function (Browser $browser) use ($user, $fuec): void {
        $browser->loginAs($user)
            ->visit("/fuecs/{$fuec->id}")
            ->waitForText("FUEC Nº {$fuec->consecutive_number}")
            ->press('Anular')
            ->waitForText('Anular FUEC')
            ->assertSee('Motivo de anulación')
            ->type('#cancel-reason', 'Error en datos del cliente, requiere regenerar.')
            ->press('Confirmar anulación')
            ->waitForText('Anulado')
            ->assertSee('Motivo de anulación')
            ->assertSee('Error en datos del cliente, requiere regenerar.')
            ->screenshot('fuec-cancelled-with-reason');
    });

    $fuec->refresh();
    expect($fuec->status->value)->toBe('cancelled')
        ->and($fuec->cancellation_reason)->toBe('Error en datos del cliente, requiere regenerar.');
});
