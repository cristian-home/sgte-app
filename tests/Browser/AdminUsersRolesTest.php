<?php

use App\Enums\Role as RoleEnum;
use App\Models\User;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Role as SpatieRole;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->artisan('migrate:fresh', ['--no-interaction' => true]);
});

function adminAuthenticateAsAdmin(): User
{
    SpatieRole::firstOrCreate(['name' => RoleEnum::ADMIN->value, 'guard_name' => 'web']);
    $user = User::factory()->create([
        'email' => 'admin-dusk@sgte.app',
        'password' => bcrypt('password'),
        'is_active' => true,
    ]);
    $user->assignRole(RoleEnum::ADMIN->value);

    return $user;
}

function adminCreateOperator(string $name = 'Camila Operadora'): User
{
    SpatieRole::firstOrCreate(['name' => RoleEnum::OPERATOR->value, 'guard_name' => 'web']);
    $u = User::factory()->create([
        'name' => $name,
        'email' => str()->slug($name).'@sgte.app',
        'is_active' => true,
    ]);
    $u->assignRole(RoleEnum::OPERATOR->value);

    return $u;
}

test('admin sees the users index with table, tabs, and badges', function (): void {
    $admin = adminAuthenticateAsAdmin();
    adminCreateOperator('Camila Restrepo');
    adminCreateOperator('Andres Pardo');

    $this->browse(function (Browser $browser) use ($admin): void {
        $browser->loginAs($admin)
            ->visit('/users')
            ->waitForText('Usuarios')
            ->assertSee('Gestiona las cuentas que acceden al sistema.')
            ->assertSee('Nuevo usuario')
            ->assertSee('Roles')
            ->assertSee('Permisos')
            ->assertSee('Referencia')
            ->assertSourceHas('Buscar por nombre o correo')
            ->assertSee('Camila Restrepo')
            ->assertSee('Andres Pardo')
            ->assertDontSee('Whoops')
            ->assertDontSee('Exception')
            ->screenshot('admin-users-index');
    });
});

test('admin opens the create user modal and key fields render', function (): void {
    $admin = adminAuthenticateAsAdmin();

    $this->browse(function (Browser $browser) use ($admin): void {
        $browser->loginAs($admin)
            ->visit('/users')
            ->waitForText('Usuarios')
            ->press('Nuevo usuario')
            ->pause(500)
            ->waitForText('Crea una cuenta para que alguien acceda al sistema.')
            ->assertSee('Nombre completo')
            ->assertSee('Correo electrónico')
            ->assertSee('Contraseña')
            ->assertSee('Confirmar contraseña')
            ->assertSee('Roles')
            ->assertSee('Cuenta activa')
            ->assertSee('Enviar correo de bienvenida')
            ->assertSee('Guardar usuario')
            ->screenshot('admin-users-create-modal');
    });
});

test('admin sees roles index with 5 cards including super admin locked', function (): void {
    $admin = adminAuthenticateAsAdmin();

    $this->browse(function (Browser $browser) use ($admin): void {
        $browser->loginAs($admin)
            ->visit('/roles')
            ->waitForText('Roles')
            ->assertSee('Define qué puede hacer cada conjunto de usuarios')
            ->assertSee('Super Administrador')
            ->assertSee('Administrador')
            ->assertSee('Operación')
            ->assertSee('Conductor')
            ->assertSee('Contabilidad')
            ->assertSee('super_admin')
            ->assertSee('Ver detalles')
            ->assertDontSee('Whoops')
            ->assertDontSee('Exception')
            ->screenshot('admin-roles-index');
    });
});

test('admin sees role detail with permissions matrix', function (): void {
    $admin = adminAuthenticateAsAdmin();

    $this->browse(function (Browser $browser) use ($admin): void {
        $browser->loginAs($admin)
            ->visit('/roles/admin')
            ->waitForText('Información del rol')
            ->assertSee('Administrador')
            ->assertSee('Etiqueta')
            ->assertSee('Descripción')
            ->assertSee('Permisos')
            ->assertSee('Usuarios con este rol')
            ->assertSee('Vehículos')
            ->assertSee('Conductores')
            ->assertSee('Volver a roles')
            ->assertDontSee('Whoops')
            ->assertDontSee('Exception')
            ->screenshot('admin-role-edit-admin');
    });
});

test('super admin role detail page renders locked messaging', function (): void {
    $admin = adminAuthenticateAsAdmin();

    $this->browse(function (Browser $browser) use ($admin): void {
        $browser->loginAs($admin)
            ->visit('/roles/super_admin')
            ->waitForText('Super Administrador')
            ->assertSee('Bloqueado')
            ->assertSee('Este rol omite las verificaciones de permisos')
            ->assertDontSee('Whoops')
            ->screenshot('admin-role-super-admin-locked');
    });
});

test('admin sees permissions reference page', function (): void {
    $admin = adminAuthenticateAsAdmin();

    $this->browse(function (Browser $browser) use ($admin): void {
        $browser->loginAs($admin)
            ->visit('/permissions')
            ->waitForText('Permisos')
            ->assertSee('Referencia de todos los permisos disponibles')
            ->assertSee('Solo lectura')
            ->assertSee('Vehículos')
            ->assertSee('Conductores')
            ->assertSee('Terceros')
            ->assertSee('vehicles.view')
            ->assertSee('drivers.view')
            ->assertDontSee('Whoops')
            ->assertDontSee('Exception')
            ->screenshot('admin-permissions-reference');
    });
});

test('toggling a user active switch reflects in the UI without a hard refresh', function (): void {
    $admin = adminAuthenticateAsAdmin();
    $operator = adminCreateOperator('Camila Switch');

    $clickRowSwitch = sprintf(
        "var r = Array.from(document.querySelectorAll('table tbody tr')).find(function(x){ return x.textContent.indexOf('%s') !== -1; }); if (r) { var s = r.querySelector('button[role=switch]'); if (s) s.click(); } return !!r;",
        $operator->email,
    );

    $rowText = sprintf(
        "var r = Array.from(document.querySelectorAll('table tbody tr')).find(function(x){ return x.textContent.indexOf('%s') !== -1; }); return r ? r.textContent : '';",
        $operator->email,
    );

    $clickCount = "return performance.getEntriesByType('navigation').length + performance.getEntriesByType('reload').length;";

    $this->browse(function (Browser $browser) use ($admin, $operator, $clickRowSwitch, $rowText, $clickCount): void {
        $browser->loginAs($admin)
            ->visit('/users')
            ->waitForText('Camila Switch')
            ->assertSee($operator->email);

        $beforeText = (string) ($browser->script($rowText)[0] ?? '');
        expect(str_contains($beforeText, 'Activo'))->toBeTrue();

        $navsBefore = (int) ($browser->script($clickCount)[0] ?? 0);

        $browser->script($clickRowSwitch);
        $browser->waitUsing(8, 250, function () use ($browser, $rowText): bool {
            $text = (string) ($browser->script($rowText)[0] ?? '');

            return str_contains($text, 'Inactivo');
        }, 'expected the row to reflect "Inactivo" after toggling without a hard refresh');

        $navsAfter = (int) ($browser->script($clickCount)[0] ?? 0);
        // Async PATCH must not trigger a navigation/reload of the page.
        expect($navsAfter)->toBe($navsBefore);

        $browser->screenshot('admin-users-toggle-reflects');
    });

    expect($operator->fresh()->is_active)->toBeFalse();
});

test('admin cannot toggle their own account from the UI (switch is disabled)', function (): void {
    $admin = adminAuthenticateAsAdmin();

    $selfRowSwitchDisabled = sprintf(
        "var r = Array.from(document.querySelectorAll('table tbody tr')).find(function(x){ return x.textContent.indexOf('%s') !== -1; }); if (!r) return 'no-row'; var s = r.querySelector('button[role=switch]'); return s && s.hasAttribute('disabled') ? 'disabled' : 'enabled';",
        $admin->email,
    );

    $this->browse(function (Browser $browser) use ($admin, $selfRowSwitchDisabled): void {
        $browser->loginAs($admin)
            ->visit('/users')
            ->waitForText('Usuarios')
            ->assertSee($admin->email);

        $state = (string) ($browser->script($selfRowSwitchDisabled)[0] ?? '');
        expect($state)->toBe('disabled');

        $browser->screenshot('admin-users-self-switch-disabled');
    });

    // Sanity: the server-side guard still works regardless of UI state.
    actingAs($admin)
        ->patch(route('users.toggle-active', $admin))
        ->assertSessionHasErrors(['is_active']);
    expect($admin->fresh()->is_active)->toBeTrue();
});

test('non-admin operator gets forbidden on users index', function (): void {
    SpatieRole::firstOrCreate(['name' => RoleEnum::OPERATOR->value, 'guard_name' => 'web']);
    $op = User::factory()->create([
        'email' => 'op-dusk@sgte.app',
        'password' => bcrypt('password'),
        'is_active' => true,
    ]);
    $op->assignRole(RoleEnum::OPERATOR->value);

    $this->browse(function (Browser $browser) use ($op): void {
        $browser->loginAs($op)
            ->visit('/users')
            ->pause(1500)
            ->assertSourceHas('403');
    });
});
