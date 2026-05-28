<?php

use App\Models\User;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function (): void {
    $this->artisan('migrate:fresh', ['--no-interaction' => true]);
});

function importsAuthenticateAsSuperAdmin(): User
{
    $role = SpatieRole::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $user = User::query()->where('email', env('SUPER_ADMIN_USER'))->first();
    if (! $user) {
        $user = User::factory()->create([
            'email' => env('SUPER_ADMIN_USER'),
            'password' => bcrypt(env('SUPER_ADMIN_PASSWORD')),
        ]);
    }
    $user->assignRole($role);

    return $user;
}

function importsAuthenticateAsAdmin(): User
{
    $role = SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $user = User::factory()->create([
        'email' => 'admin-imports-dusk@sgte.app',
        'password' => bcrypt('password'),
    ]);
    $user->assignRole($role);

    return $user;
}

test('super admin sees the imports index with templates, catalogs, and retention banner', function (): void {
    $admin = importsAuthenticateAsSuperAdmin();

    $this->browse(function (Browser $browser) use ($admin): void {
        $browser->loginAs($admin)
            ->visit('/admin/imports')
            ->waitForText('Importaciones')
            ->assertSee('Plantillas')
            ->assertSee('Catálogos de referencia')
            ->assertSee('Historial')
            ->assertSee('Nueva carga')
            ->assertSee('Los archivos se eliminan automáticamente 90 días')
            ->assertSee('Descargar plantilla — Usuarios')
            ->assertSee('Descargar plantilla — Conductores')
            ->assertSee('Descargar plantilla — Terceros')
            ->assertSee('Descargar plantilla — Vehículos')
            ->assertSee('EPS')
            ->assertSee('Ciudades')
            ->assertDontSee('error')
            ->assertDontSee('Exception')
            ->screenshot('admin-imports-index-empty');
    });
});

test('admin (not super admin) is denied access to imports index', function (): void {
    $admin = importsAuthenticateAsAdmin();

    $this->browse(function (Browser $browser) use ($admin): void {
        $browser->loginAs($admin)
            ->visit('/admin/imports')
            ->assertSee('403')
            ->screenshot('admin-imports-admin-403');
    });
});

test('super admin reaches the create form with type select, file input, and checkboxes', function (): void {
    $admin = importsAuthenticateAsSuperAdmin();

    $this->browse(function (Browser $browser) use ($admin): void {
        $browser->loginAs($admin)
            ->visit('/admin/imports/create')
            ->waitForText('Nueva carga')
            ->assertSee('Tipo de carga *')
            ->assertSee('Archivo *')
            ->assertSee('Solo validar (no escribir cambios)')
            ->assertSee('Actualizar registros existentes')
            ->assertSee('Subir y procesar')
            ->assertSee('Acepta CSV o XLSX. Máximo 20 MB')
            ->screenshot('admin-imports-create-empty-form');
    });
});

test('admin sidebar does not surface Importaciones link', function (): void {
    $admin = importsAuthenticateAsAdmin();

    $this->browse(function (Browser $browser) use ($admin): void {
        $browser->loginAs($admin)
            ->visit('/dashboard')
            ->waitForText('Panel')
            ->assertSourceMissing('href="/admin/imports"')
            ->screenshot('admin-imports-sidebar-hidden-for-admin');
    });
});

// Note: super admin sidebar visibility is exercised indirectly by the
// "super admin sees the imports index" test above (which proves the user
// can reach /admin/imports) and by the corresponding Pest controller
// authorization tests. The accordion expansion needed to surface the
// link in HTML requires a brittle in-DOM click chain we don't need.
