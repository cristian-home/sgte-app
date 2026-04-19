<?php

use App\Enums\Role as RoleEnum;
use App\Models\Driver;
use App\Models\Service;
use App\Models\User;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function (): void {
    $this->artisan('migrate:fresh', ['--seed' => true, '--no-interaction' => true]);
});

function declineTestAuthenticateAsSuperAdmin(): User
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

function declineTestCreateDriverWithTodayService(): array
{
    $driverRole = SpatieRole::firstOrCreate(['name' => RoleEnum::DRIVER->value, 'guard_name' => 'web']);
    $driverUser = User::factory()->create([
        'email' => 'driver-decline-dusk@sgte.app',
        'password' => bcrypt('password'),
    ]);
    $driverUser->forceFill(['email_verified_at' => now()])->save();
    $driverUser->assignRole($driverRole);

    $driver = Driver::factory()->create(['user_id' => $driverUser->id]);
    $service = Service::factory()->create([
        'driver_id' => $driver->id,
        'service_date' => now()->toDateString(),
        'service_status' => 'open',
        'actual_start_time' => null,
    ]);

    return [$driverUser, $service];
}

test('driver sees Declinar servicio button on the dashboard (REQ-012 regression)', function (): void {
    [$driverUser, $service] = declineTestCreateDriverWithTodayService();

    $this->browse(function (Browser $browser) use ($driverUser): void {
        $browser->loginAs($driverUser)
            ->visit('/driver')
            ->waitForText('Mis Servicios')
            ->assertSee('Declinar servicio')
            ->assertSee('Confirmar Inicio')
            ->screenshot('driver-decline-button-visible');
    });
});

test('driver opens decline dialog and submits a reason (REQ-012 regression)', function (): void {
    [$driverUser, $service] = declineTestCreateDriverWithTodayService();

    $this->browse(function (Browser $browser) use ($driverUser, $service): void {
        $browser->loginAs($driverUser)
            ->visit('/driver')
            ->waitForText('Mis Servicios')
            ->press('Declinar servicio')
            ->waitForText('Motivo del rechazo')
            ->assertSee('Motivo del rechazo')
            ->type('#reason_text', 'Incapacidad médica antes de iniciar el turno.')
            ->press('Confirmar rechazo')
            ->waitForText('Servicio declinado')
            ->assertSee('Servicio declinado')
            ->assertSee('pendiente de reasignación')
            ->screenshot('driver-decline-submitted');

        $service->refresh();
        expect($service->driver_declined_at)->not->toBeNull();
    });
});

test('day summary renders Pendientes de reasignación section for declined services (REQ-012 regression)', function (): void {
    [$driverUser, $service] = declineTestCreateDriverWithTodayService();

    $service->update([
        'driver_declined_at' => now(),
        'driver_decline_reason' => 'Avería mecánica detectada antes de salir.',
    ]);

    $admin = declineTestAuthenticateAsSuperAdmin();
    $today = now()->toDateString();

    $this->browse(function (Browser $browser) use ($admin, $today): void {
        $browser->loginAs($admin)
            ->visit("/day-summary?date={$today}")
            ->waitForText('Resumen del Día')
            ->assertSee('Pendientes de reasignación')
            ->assertSee('Pend. reasignación')
            ->assertSee('Avería mecánica detectada')
            ->screenshot('day-summary-pending-reassignment');
    });
});
