<?php

use App\Enums\Role as RoleEnum;
use App\Models\User;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function (): void {
    $this->artisan('migrate:fresh', ['--no-interaction' => true]);
});

function driverFlowLoginAsAdmin(): User
{
    SpatieRole::firstOrCreate(['name' => RoleEnum::ADMIN->value, 'guard_name' => 'web']);
    $admin = User::factory()->create([
        'email' => 'admin-driverflow@sgte.app',
        'password' => bcrypt('password'),
        'is_active' => true,
    ]);
    $admin->assignRole(RoleEnum::ADMIN->value);

    return $admin;
}

test('users payload excludes the driver role from availableRoles', function (): void {
    $admin = driverFlowLoginAsAdmin();

    $this->browse(function (Browser $browser) use ($admin): void {
        $browser->loginAs($admin)
            ->visit('/users')
            ->waitForText('Usuarios')
            // El JSON serializado por Inertia vive en <div id="app" data-page=…>.
            // Las opciones de rol incluyen admin, operator, accounting pero NO driver.
            ->assertSourceHas('&quot;value&quot;:&quot;admin&quot;')
            ->assertSourceHas('&quot;value&quot;:&quot;operator&quot;')
            ->assertSourceHas('&quot;value&quot;:&quot;accounting&quot;')
            ->assertSourceMissing('&quot;value&quot;:&quot;driver&quot;')
            ->screenshot('users-without-driver-role');
    });
});

test('drivers create modal renders the account section with the checkbox', function (): void {
    $admin = driverFlowLoginAsAdmin();

    $this->browse(function (Browser $browser) use ($admin): void {
        $browser->loginAs($admin)
            ->visit('/drivers')
            ->waitForText('Crear Conductor')
            ->press('Crear Conductor')
            ->waitForText('Complete los campos para registrar un nuevo conductor.')
            // The "Cuenta de acceso al sistema" section only renders when
            // DriverForm receives mode="create" — this guards the create
            // modal against silently dropping the account-creation option.
            ->assertSee('Cuenta de acceso al sistema')
            ->assertSee('Crear cuenta de acceso para este conductor')
            ->assertSee('Se enviará un enlace al correo')
            ->screenshot('drivers-create-account-section');
    });
});
