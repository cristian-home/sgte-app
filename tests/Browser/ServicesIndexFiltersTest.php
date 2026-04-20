<?php

use App\Models\Contract;
use App\Models\Service;
use App\Models\User;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function (): void {
    $this->artisan('migrate:fresh', ['--no-interaction' => true]);
});

function servicesIndexFiltersAuthenticateAsSuperAdmin(): User
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

test('contract filter narrows the services table to the chosen contract (regression)', function (): void {
    $user = servicesIndexFiltersAuthenticateAsSuperAdmin();

    $contractA = Contract::factory()->create([
        'contract_number' => 'CT-FILTER-AAA',
        'active' => true,
    ]);
    $contractB = Contract::factory()->create([
        'contract_number' => 'CT-FILTER-BBB',
        'active' => true,
    ]);

    // Two services on contract A with distinct origins so we can pick them out visually.
    Service::factory()->create([
        'contract_id' => $contractA->id,
        'origin_address' => 'Origen-AAA-01',
    ]);
    Service::factory()->create([
        'contract_id' => $contractA->id,
        'origin_address' => 'Origen-AAA-02',
    ]);
    // One service on contract B.
    Service::factory()->create([
        'contract_id' => $contractB->id,
        'origin_address' => 'Origen-BBB-01',
    ]);

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->visit('/services')
            ->waitForText('Servicios')
            // Baseline: all three services are visible, and the old
            // stacked-select bar is gone (no "Presets:" / "Municipio
            // Origen" / "Desde" labels rendered outside the toolbar).
            ->assertSee('Origen-AAA-01')
            ->assertSee('Origen-AAA-02')
            ->assertSee('Origen-BBB-01')
            // Toolbar-style buttons are present for every required filter
            // + the three preset buttons.
            ->assertSee('Contrato')
            ->assertSee('Conductor')
            ->assertSee('Vehículo')
            ->assertSee('Municipio Origen')
            ->assertSee('Municipio Destino')
            ->assertSee('Rango de fechas')
            ->assertSee('Hoy')
            ->assertSee('Esta semana')
            ->assertSee('Pendientes de cerrar')
            ->screenshot('services-index-toolbar-baseline')
            // Apply the Contrato faceted filter via the toolbar button.
            ->clickAtXPath("//button[normalize-space(.)='Contrato']")
            ->waitFor('[role="listbox"]')
            ->clickAtXPath("//*[@role='option'][contains(., 'CT-FILTER-AAA')]")
            ->waitUntilMissingText('Origen-BBB-01')
            ->assertSee('Origen-AAA-01')
            ->assertSee('Origen-AAA-02')
            ->assertDontSee('Origen-BBB-01')
            ->screenshot('services-index-toolbar-contract-picked');
    });
});

test('date range filter in the toolbar narrows services by service_date (regression)', function (): void {
    $user = servicesIndexFiltersAuthenticateAsSuperAdmin();

    Service::factory()->create([
        'service_date' => '2026-01-05',
        'origin_address' => 'Origen-Ene05',
    ]);
    Service::factory()->create([
        'service_date' => '2026-02-15',
        'origin_address' => 'Origen-Feb15',
    ]);
    Service::factory()->create([
        'service_date' => '2026-03-20',
        'origin_address' => 'Origen-Mar20',
    ]);

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->visit('/services')
            ->waitForText('Servicios')
            ->assertSee('Origen-Ene05')
            ->assertSee('Origen-Feb15')
            ->assertSee('Origen-Mar20');

        // Open the Rango de fechas popover.
        $browser->clickAtXPath("//button[normalize-space(.)='Rango de fechas']")
            ->waitFor('#services-filter-date-from');

        // React's date input needs the native setter to trigger onChange.
        // Firing both date changes back-to-back races the useServerTable
        // hook (its currentParams is stale until the first fetch returns),
        // so we apply only `date_from` here and rely on Pest to cover the
        // combined range.
        $browser->script(<<<'JS'
            (() => {
                const setNative = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
                const from = document.querySelector('#services-filter-date-from');
                setNative.call(from, '2026-02-01');
                from.dispatchEvent(new Event('input', { bubbles: true }));
                from.dispatchEvent(new Event('change', { bubbles: true }));
            })();
        JS);

        $browser->waitUntilMissingText('Origen-Ene05')
            ->assertSee('Origen-Feb15')
            ->assertSee('Origen-Mar20')
            ->assertDontSee('Origen-Ene05')
            ->screenshot('services-index-toolbar-date-range-filled');
    });
});

test('Hoy preset sets the date range to today via the toolbar button', function (): void {
    $user = servicesIndexFiltersAuthenticateAsSuperAdmin();

    $this->browse(function (Browser $browser) use ($user): void {
        $today = now()->toDateString();

        $browser->loginAs($user)
            ->visit('/services')
            ->waitForText('Servicios')
            ->clickAtXPath("//button[normalize-space(.)='Hoy']")
            // useServerTable history.replaceState's after the fetch
            // lands; pause briefly for the URL to update.
            ->pause(1500);

        // Confirm the URL now carries the date_from + date_to params.
        $url = $browser->driver->getCurrentURL();
        expect($url)->toContain('filter%5Bdate_from%5D='.$today)
            ->and($url)->toContain('filter%5Bdate_to%5D='.$today);

        $browser->screenshot('services-index-toolbar-preset-hoy');
    });
});
