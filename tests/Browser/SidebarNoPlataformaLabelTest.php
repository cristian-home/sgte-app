<?php

use App\Models\User;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function (): void {
    $this->artisan('migrate:fresh', ['--seed' => true, '--no-interaction' => true]);
});

function plataformaRelabelAuthenticateAsSuperAdmin(): User
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

test('sidebar no longer renders the generic Plataforma group label', function (): void {
    $user = plataformaRelabelAuthenticateAsSuperAdmin();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->visit('/dashboard')
            ->waitForText('Panel')
            ->assertDontSee('Plataforma')
            // Sub-group labels should still render — they carry the
            // navigation semantics the dropped "Plataforma" label was
            // supposed to provide.
            ->assertSee('Producción')
            ->assertSee('Gestión')
            ->assertSee('Catálogos')
            ->screenshot('sidebar-without-plataforma-label');
    });
});
