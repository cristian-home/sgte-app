<?php

use App\Models\User;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function (): void {
    $this->artisan('migrate:fresh', ['--seed' => true, '--no-interaction' => true]);
});

function daySummaryAuthenticateAsSuperAdmin(): User
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

test('day summary page displays services table with expected columns', function (): void {
    $user = daySummaryAuthenticateAsSuperAdmin();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->visit('/day-summary')
            ->waitForText('Resumen del Día')
            ->assertSee('Placa')
            ->assertSee('Conductor/Proveedor')
            ->assertSee('Horario')
            ->assertSee('Cliente')
            ->assertSee('Estado')
            ->assertSee('Novedades')
            ->screenshot('day-summary-table');
    });
});

test('day summary page displays executive summary stats', function (): void {
    $user = daySummaryAuthenticateAsSuperAdmin();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->visit('/day-summary')
            ->waitForText('Resumen del Día')
            ->assertSee('Total Servicios')
            ->assertSee('Cerrados')
            ->assertSee('Abiertos')
            ->assertSee('Con Novedades')
            ->assertSee('Vehículos 3ros')
            ->screenshot('day-summary-stats');
    });
});

test('day summary page has export CSV button', function (): void {
    $user = daySummaryAuthenticateAsSuperAdmin();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->visit('/day-summary')
            ->waitForText('Resumen del Día')
            ->assertSee('Exportar CSV')
            ->screenshot('day-summary-export');
    });
});

test('day summary page has day navigation arrows', function (): void {
    $user = daySummaryAuthenticateAsSuperAdmin();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->visit('/day-summary?date=2026-03-06')
            ->waitForText('Resumen del Día')
            ->assertSee('Ver Gantt')
            ->screenshot('day-summary-navigation');
    });
});

test('day summary page shows no errors', function (): void {
    $user = daySummaryAuthenticateAsSuperAdmin();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->visit('/day-summary')
            ->waitForText('Resumen del Día')
            ->assertDontSee('Error')
            ->assertDontSee('Exception')
            ->assertDontSee('Stack trace')
            ->screenshot('day-summary-no-errors');
    });
});
