<?php

use App\Models\User;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function (): void {
    $this->artisan('migrate:fresh', ['--seed' => true, '--no-interaction' => true]);
});

function screenshotAuthenticateAsSuperAdmin(): User
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

function enableDarkMode(Browser $browser): void
{
    $browser->script("document.documentElement.classList.add('dark')");
    $browser->pause(500);
}

function waitForAssetsLoaded(Browser $browser): void
{
    $browser->waitUsing(10, 100, fn () => $browser->driver->executeScript(
        "return document.readyState === 'complete' && document.fonts.ready.then(() => true)"
    ));
    $browser->pause(500);
}

test('screenshot: annual calendar light 1920x1080', function (): void {
    $user = screenshotAuthenticateAsSuperAdmin();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->resize(1920, 1080)
            ->visit('/day-statuses')
            ->waitForText('Enero');

        waitForAssetsLoaded($browser);

        $browser->screenshot('calendar-annual-light-1920x1080');
    });
});

test('screenshot: annual calendar dark 1920x1080', function (): void {
    $user = screenshotAuthenticateAsSuperAdmin();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->resize(1920, 1080)
            ->visit('/day-statuses')
            ->waitForText('Enero');

        enableDarkMode($browser);
        waitForAssetsLoaded($browser);

        $browser->screenshot('calendar-annual-dark-1920x1080');
    });
});

test('screenshot: annual calendar light 1280x720', function (): void {
    $user = screenshotAuthenticateAsSuperAdmin();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->resize(1280, 720)
            ->visit('/day-statuses')
            ->waitForText('Enero');

        waitForAssetsLoaded($browser);

        $browser->screenshot('calendar-annual-light-1280x720');
    });
});

test('screenshot: annual calendar dark 1280x720', function (): void {
    $user = screenshotAuthenticateAsSuperAdmin();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->resize(1280, 720)
            ->visit('/day-statuses')
            ->waitForText('Enero');

        enableDarkMode($browser);
        waitForAssetsLoaded($browser);

        $browser->screenshot('calendar-annual-dark-1280x720');
    });
});

test('screenshot: month detail light 1920x1080', function (): void {
    $user = screenshotAuthenticateAsSuperAdmin();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->resize(1920, 1080)
            ->visit('/day-statuses')
            ->waitForText('Enero')
            ->click('[data-dusk="month-2"]')
            ->waitForText('Lun');

        waitForAssetsLoaded($browser);

        $browser->screenshot('calendar-month-detail-light-1920x1080');
    });
});

test('screenshot: month detail dark 1920x1080', function (): void {
    $user = screenshotAuthenticateAsSuperAdmin();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->resize(1920, 1080)
            ->visit('/day-statuses')
            ->waitForText('Enero')
            ->click('[data-dusk="month-2"]')
            ->waitForText('Lun');

        enableDarkMode($browser);
        waitForAssetsLoaded($browser);

        $browser->screenshot('calendar-month-detail-dark-1920x1080');
    });
});

test('screenshot: month detail light 1280x720', function (): void {
    $user = screenshotAuthenticateAsSuperAdmin();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->resize(1280, 720)
            ->visit('/day-statuses')
            ->waitForText('Enero')
            ->click('[data-dusk="month-2"]')
            ->waitForText('Lun');

        waitForAssetsLoaded($browser);

        $browser->screenshot('calendar-month-detail-light-1280x720');
    });
});

test('screenshot: month detail dark 1280x720', function (): void {
    $user = screenshotAuthenticateAsSuperAdmin();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->resize(1280, 720)
            ->visit('/day-statuses')
            ->waitForText('Enero')
            ->click('[data-dusk="month-2"]')
            ->waitForText('Lun');

        enableDarkMode($browser);
        waitForAssetsLoaded($browser);

        $browser->screenshot('calendar-month-detail-dark-1280x720');
    });
});
