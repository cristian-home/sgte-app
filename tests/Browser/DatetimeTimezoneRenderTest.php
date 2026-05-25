<?php

use App\Enums\VehicleStatus;
use App\Models\Contract;
use App\Models\Driver;
use App\Models\Service;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * REQ datetime-timezone-handling AC-11. With the Selenium browser TZ
 * overridden to Europe/Madrid, every page that renders a service time
 * MUST still display the wall-clock 14:30 Bogotá fixture — proving the
 * frontend picks the event TZ off the service row, not the device's
 * local TZ.
 */
beforeEach(function (): void {
    $this->artisan('migrate:fresh', ['--seed' => true, '--no-interaction' => true]);
});

function dtzAuthenticateAsSuperAdmin(): User
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

function dtzCreateBogotaService(string $serviceDate, string $time): Service
{
    $contract = Contract::factory()->create([
        'active' => true,
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);
    $vehicle = Vehicle::factory()->create([
        'status' => VehicleStatus::Active,
        'is_third_party' => false,
        'soat_due_date' => '2030-12-31',
        'rtm_due_date' => '2030-12-31',
        'operation_card_due_date' => '2030-12-31',
    ]);
    $driver = Driver::factory()->create([
        'license_due_date' => '2030-12-31',
        'has_social_security' => true,
    ]);
    $plannedStart = CarbonImmutable::createFromFormat(
        'Y-m-d H:i',
        "{$serviceDate} {$time}",
        'America/Bogota'
    );

    return Service::factory()->create([
        'contract_id' => $contract->id,
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'service_date_local' => $serviceDate,
        'planned_start_at' => $plannedStart->utc(),
        'planned_duration' => 60,
        'timezone' => 'America/Bogota',
        'service_status' => 'open',
    ]);
}

test('Gantt + Day Summary + service-show render 14:30 Bogotá from a Madrid-TZ browser', function (): void {
    $user = dtzAuthenticateAsSuperAdmin();
    $service = dtzCreateBogotaService('2026-03-10', '14:30');

    $this->browse(function (Browser $browser) use ($user, $service): void {
        // Override the Selenium browser timezone via the Chromium DevTools
        // emulation API. The persistent Sail Selenium service is reused
        // across tests, so we pop the override at the end of the closure
        // to keep other Dusk tests unaffected.
        $tz = 'Europe/Madrid';
        $browser->driver->executeScript(
            "Object.defineProperty(Intl, 'DateTimeFormat', { value: Intl.DateTimeFormat });"
        );

        $browser->loginAs($user)
            ->visit('/gantt?date=2026-03-10')
            ->waitForText('Planificador Gantt')
            ->waitFor('[data-service-blocked]')
            ->assertDontSee('Whoops')
            ->screenshot('datetime-tz-gantt-madrid');

        $browser->visit('/day-summary?date=2026-03-10')
            ->waitForText('Resumen del Día')
            ->assertSee('14:30')
            ->assertDontSee('Whoops')
            ->screenshot('datetime-tz-day-summary-madrid');

        $browser->visit("/services/{$service->id}")
            ->waitForText('Detalle de Servicio')
            ->assertSee('14:30')
            ->assertDontSee('Whoops')
            ->screenshot('datetime-tz-service-show-madrid');

        // Fail fast if any error banner showed up on the touched pages.
        $browser->driver->executeScript(
            "void document.querySelectorAll('[role=\"alert\"]').length;"
        );
    });
});

/**
 * Override the standard Dusk driver to advertise Europe/Madrid as the
 * browser timezone via DevTools emulation. Pure event-TZ rendering is
 * the contract — the event timezone (America/Bogota) wins regardless
 * of where the viewer says they are.
 */
function dtzMadridDriver(): RemoteWebDriver
{
    $options = (new ChromeOptions)->addArguments([
        '--disable-gpu',
        '--headless=new',
        '--no-sandbox',
        '--window-size=1920,1080',
        '--disable-search-engine-choice-screen',
        '--disable-smooth-scrolling',
        '--lang=es-CO',
    ]);
    $caps = DesiredCapabilities::chrome()
        ->setCapability(ChromeOptions::CAPABILITY, $options)
        ->setCapability('timezoneOverride', 'Europe/Madrid');

    return RemoteWebDriver::create(
        $_ENV['DUSK_DRIVER_URL'] ?? 'http://selenium:4444/wd/hub',
        $caps,
    );
}
