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

test('visiting /day-statuses redirects to /day-statuses/{year}', function (): void {
    $user = calendarAuthenticateAsSuperAdmin();
    $year = now()->year;

    $this->browse(function (Browser $browser) use ($user, $year): void {
        $browser->loginAs($user)
            ->visit('/day-statuses')
            ->waitForText('Enero')
            ->assertPathIs('/day-statuses/'.$year);
    });
});

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

test('clicking a month navigates to month URL and shows detail view', function (): void {
    $user = calendarAuthenticateAsSuperAdmin();
    $year = now()->year;

    $this->browse(function (Browser $browser) use ($user, $year): void {
        $browser->loginAs($user)
            ->visit('/day-statuses')
            ->waitForText('Enero')
            ->click('[data-dusk="month-0"]')
            ->waitForText('Lun')
            ->assertPathIs('/day-statuses/'.$year.'/1')
            ->assertSee('Lun')
            ->assertSee('Mar')
            ->assertSee('Dom');
    });
});

test('month detail has prev/next navigation arrows', function (): void {
    $user = calendarAuthenticateAsSuperAdmin();
    $year = now()->year;

    $this->browse(function (Browser $browser) use ($user, $year): void {
        $browser->loginAs($user)
            ->visit('/day-statuses/'.$year.'/3')
            ->waitForText('Marzo')
            ->assertPresent('[data-dusk="prev-month"]')
            ->assertPresent('[data-dusk="next-month"]');
    });
});

test('clicking next month arrow navigates to next month', function (): void {
    $user = calendarAuthenticateAsSuperAdmin();
    $year = now()->year;

    $this->browse(function (Browser $browser) use ($user, $year): void {
        $browser->loginAs($user)
            ->visit('/day-statuses/'.$year.'/3')
            ->waitForText('Marzo')
            ->click('[data-dusk="next-month"]')
            ->waitForText('Abril')
            ->assertPathIs('/day-statuses/'.$year.'/4')
            ->assertSee('Abril');
    });
});

test('clicking prev month arrow navigates to previous month', function (): void {
    $user = calendarAuthenticateAsSuperAdmin();
    $year = now()->year;

    $this->browse(function (Browser $browser) use ($user, $year): void {
        $browser->loginAs($user)
            ->visit('/day-statuses/'.$year.'/3')
            ->waitForText('Marzo')
            ->click('[data-dusk="prev-month"]')
            ->waitForText('Febrero')
            ->assertPathIs('/day-statuses/'.$year.'/2')
            ->assertSee('Febrero');
    });
});

test('month navigation handles year boundary going backward', function (): void {
    $user = calendarAuthenticateAsSuperAdmin();
    $year = now()->year;
    $prevYear = $year - 1;

    $this->browse(function (Browser $browser) use ($user, $year, $prevYear): void {
        $browser->loginAs($user)
            ->visit('/day-statuses/'.$year.'/1')
            ->waitForText('Enero')
            ->click('[data-dusk="prev-month"]')
            ->waitForText('Diciembre')
            ->assertPathIs('/day-statuses/'.$prevYear.'/12')
            ->assertSee('Diciembre');
    });
});

test('month navigation handles year boundary going forward', function (): void {
    $user = calendarAuthenticateAsSuperAdmin();
    $year = now()->year;
    $nextYear = $year + 1;

    $this->browse(function (Browser $browser) use ($user, $year, $nextYear): void {
        $browser->loginAs($user)
            ->visit('/day-statuses/'.$year.'/12')
            ->waitForText('Diciembre')
            ->click('[data-dusk="next-month"]')
            ->waitForText('Enero')
            ->assertPathIs('/day-statuses/'.$nextYear.'/1')
            ->assertSee('Enero');
    });
});

test('clicking month title navigates back to annual view', function (): void {
    $user = calendarAuthenticateAsSuperAdmin();
    $year = now()->year;

    $this->browse(function (Browser $browser) use ($user, $year): void {
        $browser->loginAs($user)
            ->visit('/day-statuses/'.$year.'/3')
            ->waitForText('Marzo')
            ->click('[data-dusk="back-to-year"]')
            ->waitFor('[data-dusk="month-0"]')
            ->assertPathIs('/day-statuses/'.$year)
            ->assertSee('Enero');
    });
});

test('clicking a day in monthly view loads services inline', function (): void {
    $user = calendarAuthenticateAsSuperAdmin();
    $year = now()->year;

    $this->browse(function (Browser $browser) use ($user, $year): void {
        $browser->loginAs($user)
            ->visit('/day-statuses/'.$year.'/1')
            ->waitForText('Lun')
            ->click("[data-dusk=\"day-{$year}-01-15\"]")
            ->waitForText("Servicios del {$year}-01-15")
            ->assertPathIs('/day-statuses/'.$year.'/1')
            ->assertSee("Servicios del {$year}-01-15");
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
            ->assertPathIs('/day-statuses/'.$nextYear)
            ->assertSee((string) $nextYear);
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
