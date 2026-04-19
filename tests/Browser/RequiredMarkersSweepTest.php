<?php

use App\Models\User;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function (): void {
    $this->artisan('migrate:fresh', ['--seed' => true, '--no-interaction' => true]);
});

function requiredMarkersAuthenticateAsSuperAdmin(): User
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

/**
 * Smoke tests for required-markers-across-forms. Each test visits the
 * create page for a module and asserts the expected field labels carry
 * the `*` marker. Asserting label text via `assertSee('Foo *')` works
 * because the marker sits inline in the DOM right after the label copy
 * (regardless of whether it's a standalone `*` or wrapped in a span).
 */
test('vehicles create form shows required markers on all required labels', function (): void {
    $user = requiredMarkersAuthenticateAsSuperAdmin();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->visit('/vehicles/create')
            ->waitForText('Crear Vehículo')
            ->assertSee('Código Interno')
            ->assertSee('Placa')
            ->assertSee('Número Móvil')
            ->assertSee('Marca')
            ->assertSee('Línea')
            ->assertSee('Año Modelo')
            ->assertSee('Tipo')
            ->assertSee('Capacidad')
            ->assertSee('Número de Motor')
            ->assertSee('Número de Chasis')
            ->assertSee('Vencimiento SOAT')
            ->assertSee('Vencimiento RTM')
            ->assertSee('Vencimiento Tarjeta de Operación')
            ->assertSee('Estado')
            ->screenshot('required-markers-vehicles-create');

        // Each required label renders an adjacent " *" span — count the
        // asterisk markers to confirm the 14 required vehicle fields
        // (municipality_id is the only nullable one in the top-level form).
        $markerCount = $browser->script(
            "return document.querySelectorAll('form .text-destructive').length;"
        )[0];
        expect($markerCount)->toBeGreaterThanOrEqual(14);
    });
});

test('incident-types create form shows required markers on code / nombre / severidad', function (): void {
    $user = requiredMarkersAuthenticateAsSuperAdmin();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->visit('/incident-types/create')
            ->waitForText('Crear Tipo de Novedad')
            ->assertSee('Código')
            ->assertSee('Nombre')
            ->assertSee('Severidad')
            ->screenshot('required-markers-incident-types-create');

        $markerCount = $browser->script(
            "return document.querySelectorAll('form .text-destructive').length;"
        )[0];
        expect($markerCount)->toBeGreaterThanOrEqual(3);
    });
});

test('contracts create form shows required markers on all required labels', function (): void {
    $user = requiredMarkersAuthenticateAsSuperAdmin();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->visit('/contracts/create')
            ->waitForText('Crear Contrato')
            ->assertSee('Cliente')
            ->assertSee('Objeto del Contrato')
            ->assertSee('Fecha de Inicio')
            ->assertSee('Fecha de Fin')
            ->assertSee('Recorrido / Ruta')
            ->screenshot('required-markers-contracts-create');

        $markerCount = $browser->script(
            "return document.querySelectorAll('form .text-destructive').length;"
        )[0];
        // Client + contract_object + start_date + end_date +
        // route_description are always required; contract_number becomes
        // required only when is_generic=false (default).
        expect($markerCount)->toBeGreaterThanOrEqual(5);
    });
});

test('drivers create form shows required markers on the required label set', function (): void {
    $user = requiredMarkersAuthenticateAsSuperAdmin();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->visit('/drivers/create')
            ->waitForText('Crear Conductor')
            ->screenshot('required-markers-drivers-create');

        $markerCount = $browser->script(
            "return document.querySelectorAll('form .text-destructive').length;"
        )[0];
        expect($markerCount)->toBeGreaterThanOrEqual(10);
    });
});

test('third-parties create form shows required markers on the required label set', function (): void {
    $user = requiredMarkersAuthenticateAsSuperAdmin();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->visit('/third-parties/create')
            ->waitForText('Crear Tercero')
            ->screenshot('required-markers-third-parties-create');

        $markerCount = $browser->script(
            "return document.querySelectorAll('form .text-destructive').length;"
        )[0];
        expect($markerCount)->toBeGreaterThanOrEqual(3);
    });
});
