<?php

use App\Models\User;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function (): void {
    $this->artisan('migrate:fresh', ['--seed' => true, '--no-interaction' => true]);
});

function authenticateAsSuperAdminForDetail(): User
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

test('service detail page displays all card sections', function (): void {
    $user = authenticateAsSuperAdminForDetail();
    $service = \App\Models\Service::first();

    if (! $service) {
        $this->markTestSkipped('Seed data required: at least one service');
    }

    $this->browse(function (Browser $browser) use ($user, $service): void {
        $browser->loginAs($user)
            ->visit('/services/'.$service->id)
            ->assertSee('Detalle de Servicio')
            ->assertSee('Datos Generales del Servicio')
            ->assertSee('Detalle de la Ruta')
            ->assertSee('Cronograma y Tiempos')
            ->assertSee('Resumen de Facturacion')
            ->assertSee('Incidentes')
            ->assertSee('Editar')
            ->assertSee('Volver')
            ->assertDontSee('Server Error')
            ->assertDontSee('500')
            ->screenshot('service-detail-overview');
    });
});

test('service detail page shows empty state when no incidents', function (): void {
    $user = authenticateAsSuperAdminForDetail();
    $service = \App\Models\Service::doesntHave('serviceIncidents')->first();

    if (! $service) {
        $this->markTestSkipped('Seed data required: a service without incidents');
    }

    $this->browse(function (Browser $browser) use ($user, $service): void {
        $browser->loginAs($user)
            ->visit('/services/'.$service->id)
            ->assertSee('No se han registrado incidentes')
            ->screenshot('service-detail-no-incidents');
    });
});

test('service detail page shows incidents list when incidents exist', function (): void {
    $user = authenticateAsSuperAdminForDetail();
    $service = \App\Models\Service::has('serviceIncidents')->first();

    if (! $service) {
        $service = \App\Models\Service::first();
        if (! $service) {
            $this->markTestSkipped('Seed data required: at least one service');
        }
        \App\Models\ServiceIncident::factory()->create(['service_id' => $service->id]);
    }

    $this->browse(function (Browser $browser) use ($user, $service): void {
        $browser->loginAs($user)
            ->visit('/services/'.$service->id)
            ->assertSee('Incidentes')
            ->assertSee('Tipo')
            ->assertSee('Descripcion')
            ->assertSee('Fecha Reporte')
            ->screenshot('service-detail-incidents');
    });
});
