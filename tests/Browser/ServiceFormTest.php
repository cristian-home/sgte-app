<?php

use App\Models\User;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function (): void {
    $this->artisan('migrate:fresh', ['--seed' => true, '--no-interaction' => true]);
});

function authenticateAsSuperAdmin(): User
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

test('service create page loads with form sections and fields', function (): void {
    $user = authenticateAsSuperAdmin();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->visit('/services/create')
            ->assertSee('Crear Servicio')
            ->assertSee('Datos del Servicio')
            ->assertSee('Fecha del Servicio')
            ->assertSee('Contrato')
            ->assertSee('Vehiculo')
            ->assertSee('Estado')
            ->assertSee('Origen y Destino')
            ->assertSee('Municipio Origen')
            ->assertSee('Direccion Origen')
            ->assertSee('Municipio Destino')
            ->assertSee('Direccion Destino')
            ->assertSee('Horarios')
            ->assertSee('Hora Inicio Planificada')
            ->assertSee('Duracion Planificada (min)')
            ->assertSee('Hora Inicio Real')
            ->assertSee('Hora Fin Real')
            ->assertSee('Duracion Real')
            ->assertSee('Facturacion')
            ->assertSee('Grupo de Facturacion')
            ->assertSee('Valor Unitario (COP)')
            ->assertSee('Cantidad')
            ->assertSee('Metodo de Pago')
            ->assertPresent('#service_date')
            ->assertPresent('#planned_start_time')
            ->assertPresent('#planned_duration')
            ->assertPresent('#unit_value')
            ->assertPresent('#quantity')
            ->assertSee('Guardar')
            ->assertSee('Cancelar');
    });
});

test('service show page displays all sections and action buttons', function (): void {
    $user = authenticateAsSuperAdmin();
    $service = \App\Models\Service::first();

    if (! $service) {
        $this->markTestSkipped('Seed data required: at least one service');
    }

    $this->browse(function (Browser $browser) use ($user, $service): void {
        $browser->loginAs($user)
            ->visit('/services/'.$service->id)
            ->assertSee('Detalle de Servicio')
            ->assertSee('Datos Generales del Servicio')
            ->assertSee('Fecha')
            ->assertSee('Contrato')
            ->assertSee('Estado')
            ->assertSee('Vehiculo')
            ->assertSee('Detalle de la Ruta')
            ->assertSee('Cronograma y Tiempos')
            ->assertSee('Hora Inicio Planificada')
            ->assertSee('Duracion Planificada')
            ->assertSee('Resumen de Facturacion')
            ->assertSee('Valor Unitario')
            ->assertSee('Metodo de Pago')
            ->assertSee('Editar')
            ->assertSee('Volver');
    });
});

test('service edit page loads with pre-filled form data', function (): void {
    $user = authenticateAsSuperAdmin();
    $service = \App\Models\Service::first();

    if (! $service) {
        $this->markTestSkipped('Seed data required: at least one service');
    }

    $this->browse(function (Browser $browser) use ($user, $service): void {
        $browser->loginAs($user)
            ->visit('/services/'.$service->id.'/edit')
            ->assertSee('Editar Servicio')
            ->assertSee('Datos del Servicio')
            ->assertPresent('#service_date')
            ->assertPresent('#planned_start_time')
            ->assertPresent('#planned_duration')
            ->assertPresent('#unit_value')
            ->assertPresent('#quantity')
            ->assertSee('Actualizar')
            ->assertSee('Cancelar');
    });
});

test('cancel button on create form navigates to services index', function (): void {
    $user = authenticateAsSuperAdmin();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->visit('/services/create')
            ->clickLink('Cancelar')
            ->waitForLocation('/services')
            ->assertPathIs('/services');
    });
});

test('show page edit button navigates to edit page', function (): void {
    $user = authenticateAsSuperAdmin();
    $service = \App\Models\Service::first();

    if (! $service) {
        $this->markTestSkipped('Seed data required: at least one service');
    }

    $this->browse(function (Browser $browser) use ($user, $service): void {
        $browser->loginAs($user)
            ->visit('/services/'.$service->id)
            ->clickLink('Editar')
            ->waitForLocation('/services/'.$service->id.'/edit')
            ->assertPathIs('/services/'.$service->id.'/edit')
            ->waitForText('Editar Servicio');
    });
});

test('show page volver button navigates to services index', function (): void {
    $user = authenticateAsSuperAdmin();
    $service = \App\Models\Service::first();

    if (! $service) {
        $this->markTestSkipped('Seed data required: at least one service');
    }

    $this->browse(function (Browser $browser) use ($user, $service): void {
        $browser->loginAs($user)
            ->visit('/services/'.$service->id)
            ->clickLink('Volver')
            ->waitForLocation('/services')
            ->assertPathIs('/services');
    });
});

test('third-party vehicle hides driver field and shows provider info', function (): void {
    $user = authenticateAsSuperAdmin();
    $vehicle = \App\Models\Vehicle::query()->where('is_third_party', true)->where('status', 'active')->first();

    if (! $vehicle) {
        $this->markTestSkipped('Seed data required: third-party active vehicle');
    }

    $service = \App\Models\Service::query()->where('vehicle_id', $vehicle->id)->first();
    if (! $service) {
        $service = \App\Models\Service::factory()->create(['vehicle_id' => $vehicle->id, 'driver_id' => null]);
    }

    $this->browse(function (Browser $browser) use ($user, $service): void {
        $browser->loginAs($user)
            ->visit('/services/'.$service->id.'/edit')
            ->assertSee('Editar Servicio')
            ->assertSee('Proveedor (Tercero)')
            ->assertDontSee('Seleccionar conductor...');
    });
});
