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
            ->assertSee('Vehículo')
            ->assertSee('Estado')
            ->assertSee('Origen y Destino')
            // Per the LocationField refactor the form has single labels
            // "Origen" / "Destino" instead of separate Municipio + Dirección.
            ->assertSee('Origen')
            ->assertSee('Destino')
            ->assertSee('Horarios')
            ->assertSee('Hora Inicio Planificada')
            ->assertSee('Duración Planificada (min)')
            // Hora Inicio Real / Hora Fin Real / Duración Real are now
            // conditionally rendered when service_status === 'closed'.
            // Their visibility-on-close behavior is covered by the
            // retroactive-justification + same-day-closed tests below.
            ->assertSee('Facturación')
            ->assertSee('Grupo de Facturación')
            ->assertSee('Valor Unitario (COP)')
            ->assertSee('Cantidad')
            ->assertSee('Método de Pago')
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
            ->assertSee('Vehículo')
            ->assertSee('Detalle de la Ruta')
            ->assertSee('Cronograma y Tiempos')
            ->assertSee('Hora Inicio Planificada')
            ->assertSee('Duración Planificada')
            ->assertSee('Resumen de Facturación')
            ->assertSee('Valor Unitario')
            ->assertSee('Método de Pago')
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

test('submitting empty service form surfaces Spanish attribute names (F-004 regression)', function (): void {
    $user = authenticateAsSuperAdmin();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->visit('/services/create')
            ->waitForText('Crear Servicio')
            ->press('Guardar')
            ->waitForText('es obligatorio')
            ->assertSee('fecha del servicio')
            ->assertSee('contrato')
            ->assertSee('vehículo')
            ->assertSee('hora de inicio planificada')
            ->assertSee('duración planificada')
            ->assertSee('valor unitario')
            ->assertDontSee('service date es obligatorio')
            ->assertDontSee('contract id es obligatorio')
            ->assertDontSee('vehicle id es obligatorio')
            ->assertDontSee('planned start time es obligatorio')
            ->assertDontSee('planned duration es obligatorio')
            ->assertDontSee('unit value es obligatorio')
            ->screenshot('audit-F-004-service-form-spanish-attrs');
    });
});

test('service form surfaces retroactive justification block on past-date + closed (REQ-009 regression)', function (): void {
    $user = authenticateAsSuperAdmin();

    $this->browse(function (Browser $browser) use ($user): void {
        $yesterday = \Illuminate\Support\Carbon::yesterday()->toDateString();

        $browser->loginAs($user)
            ->visit('/services/create')
            ->waitForText('Crear Servicio')
            ->assertDontSee('Registro retroactivo')
            ->assertDontSee('Justificación de registro retroactivo');

        // React doesn't react to `input.value=...`; use the native
        // HTMLInputElement setter so the synthetic event system picks
        // up the change and updates form state.
        $browser->script(<<<JS
            (() => {
                const input = document.querySelector('#service_date');
                const setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
                setter.call(input, '$yesterday');
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
            })();
        JS);

        // service_status is a Radix ToggleGroup (not a Select) — click the
        // "Cerrado" toggle item directly. Refactor circa post-Q5: the field
        // was changed from a Select dropdown to inline toggle buttons.
        $browser->clickAtXPath("//button[@role='radio' and normalize-space(.)='Cerrado']")
            ->waitForText('Registro retroactivo')
            ->assertSee('Registro retroactivo')
            ->assertSee('Justificación de registro retroactivo')
            ->assertPresent('#manual_entry_justification')
            ->screenshot('audit-retroactive-justification-shown');
    });
});

test('service form blocks same-day + closed with a destructive alert (REQ-009 regression)', function (): void {
    $user = authenticateAsSuperAdmin();

    $this->browse(function (Browser $browser) use ($user): void {
        $today = \Illuminate\Support\Carbon::today()->toDateString();

        $browser->loginAs($user)
            ->visit('/services/create')
            ->waitForText('Crear Servicio')
            ->assertDontSee('No se permite crear un servicio Cerrado');

        $browser->script(<<<JS
            (() => {
                const input = document.querySelector('#service_date');
                const setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
                setter.call(input, '$today');
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
            })();
        JS);

        $browser->clickAtXPath("//button[@role='radio' and normalize-space(.)='Cerrado']")
            ->waitForText('No se permite crear un servicio Cerrado')
            ->assertSee('No se permite crear un servicio Cerrado')
            ->assertDontSee('Registro retroactivo')
            ->screenshot('audit-illegal-create-as-closed-shown');
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
