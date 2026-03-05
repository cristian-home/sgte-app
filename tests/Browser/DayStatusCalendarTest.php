<?php

use App\Models\User;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function (): void {
    $this->artisan('migrate:fresh', ['--seed' => true, '--no-interaction' => true]);
});

function calendarAuthenticateAsSuperAdmin(): User
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

test('calendar page displays 12-month annual grid', function (): void {
    $user = calendarAuthenticateAsSuperAdmin();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->visit('/day-statuses')
            ->waitForText('Enero')
            ->assertSee('Enero')
            ->assertSee('Febrero')
            ->assertSee('Marzo')
            ->assertSee('Abril')
            ->assertSee('Mayo')
            ->assertSee('Junio')
            ->assertSee('Julio')
            ->assertSee('Agosto')
            ->assertSee('Septiembre')
            ->assertSee('Octubre')
            ->assertSee('Noviembre')
            ->assertSee('Diciembre')
            ->assertSee((string) now()->year);
    });
});

test('today is highlighted with a ring', function (): void {
    $user = calendarAuthenticateAsSuperAdmin();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->visit('/day-statuses')
            ->waitForText('Enero')
            ->assertPresent('[class*="ring-primary"]');
    });
});

test('clicking a month expands the monthly detail view', function (): void {
    $user = calendarAuthenticateAsSuperAdmin();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->visit('/day-statuses')
            ->waitForText('Enero')
            ->click('[data-dusk="month-0"]')
            ->waitForText('Lun')
            ->assertSee('Lun')
            ->assertSee('Mar')
            ->assertSee('Dom');
    });
});

test('clicking a day in monthly view navigates to services filtered by date', function (): void {
    $user = calendarAuthenticateAsSuperAdmin();
    $year = now()->year;

    $this->browse(function (Browser $browser) use ($user, $year): void {
        $browser->loginAs($user)
            ->visit('/day-statuses')
            ->waitForText('Enero')
            ->click('[data-dusk="month-0"]')
            ->waitForText('Lun')
            ->click("[data-dusk=\"day-{$year}-01-01\"]")
            ->waitUntilMissingText('Lun')
            ->assertPathIs('/services');

        // Verify URL contains the service_date filter (URL-encoded brackets)
        $url = $browser->driver->getCurrentURL();
        expect($url)->toContain('service_date');
    });
});

test('year navigation arrows change the displayed year', function (): void {
    $user = calendarAuthenticateAsSuperAdmin();
    $currentYear = (int) now()->year;
    $nextYear = $currentYear + 1;

    $this->browse(function (Browser $browser) use ($user, $currentYear, $nextYear): void {
        $browser->loginAs($user)
            ->visit('/day-statuses')
            ->waitForText((string) $currentYear)
            ->assertSee((string) $currentYear)
            ->click('[data-dusk="next-year"]')
            ->waitForText((string) $nextYear)
            ->assertSee((string) $nextYear)
            ->assertQueryStringHas('year', (string) $nextYear);
    });
});

test('color legend is displayed', function (): void {
    $user = calendarAuthenticateAsSuperAdmin();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->visit('/day-statuses')
            ->waitForText('Enero')
            ->assertSee('Sin servicios')
            ->assertSee('Proyectado')
            ->assertSee('Ejecutado');
    });
});
