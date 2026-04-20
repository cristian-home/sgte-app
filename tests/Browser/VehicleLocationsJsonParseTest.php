<?php

use App\Models\User;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Regression for vehicle-locations-json-parse-error-investigation.
 *
 * The original audit observed unhandled promise rejections
 * `SyntaxError: JSON.parse: unexpected character at line 1 column 1
 * of the JSON data` firing on /vehicle-locations. Root cause: the
 * controller's index() didn't have a `wantsJson()` branch, so when
 * `useServerTable` refetched with `Accept: application/json` the
 * server returned the full Inertia HTML page and `response.json()`
 * in the hook blew up.
 *
 * This Dusk navigates to the page, applies the vehicle filter (which
 * triggers the refetch path that was broken), and asserts no
 * `JSON.parse` / `SyntaxError` string leaks into the page. Browser
 * console errors would only be visible if React surfaces them, but
 * the asserted flow is the one the audit exercised.
 */
beforeEach(function (): void {
    $this->artisan('migrate:fresh', ['--seed' => true, '--no-interaction' => true]);
    config()->set('sgte.gps_enabled', true);
});

function vehicleLocationsJsonAuthenticateAsSuperAdmin(): User
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

test('vehicle-locations filter refetch returns JSON without blowing up the hook', function (): void {
    $user = vehicleLocationsJsonAuthenticateAsSuperAdmin();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->visit('/vehicle-locations')
            ->waitForText('Ubicaciones')
            ->assertSee('Vehículo')
            ->assertSee('Desde')
            ->assertSee('Hasta');

        // Verify the refetch endpoint: request /vehicle-locations with
        // `Accept: application/json` + a filter param (the shape
        // `useServerTable` produces) and assert the response is valid
        // paginator JSON — not an HTML Inertia fallback. A failure here
        // reproduces the audit's SyntaxError in the hook.
        $response = $browser->script(<<<'JS'
            return fetch('/vehicle-locations?filter[vehicle_id]=1', {
                headers: { 'Accept': 'application/json' },
            })
                .then((r) => r.text())
                .then((t) => {
                    try {
                        const parsed = JSON.parse(t);
                        return { ok: true, hasData: Array.isArray(parsed.data), keys: Object.keys(parsed).slice(0, 10) };
                    } catch (e) {
                        return { ok: false, error: String(e), preview: t.slice(0, 120) };
                    }
                });
        JS)[0];

        expect($response['ok'] ?? false)->toBeTrue();
        expect($response['hasData'] ?? false)->toBeTrue();
        expect($response['keys'] ?? [])->toContain('current_page');

        $browser->screenshot('vehicle-locations-json-refetch-ok');
    });
});
