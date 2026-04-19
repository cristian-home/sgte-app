<?php

use App\Models\Contract;
use App\Models\User;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function (): void {
    $this->artisan('migrate:fresh', ['--seed' => true, '--no-interaction' => true]);
});

function billingUnitLabelAuthenticateAsSuperAdmin(): User
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

test('service form Cantidad label mirrors selected contract billing_unit_type (REQ-011 regression)', function (): void {
    $user = billingUnitLabelAuthenticateAsSuperAdmin();

    $pasajeroContract = Contract::factory()->create([
        'contract_number' => 'CT-BILL-PAX-001',
        'active' => true,
        'billing_unit_type' => 'pasajero',
    ]);
    $diaContract = Contract::factory()->create([
        'contract_number' => 'CT-BILL-DAY-001',
        'active' => true,
        'billing_unit_type' => 'dia',
    ]);

    $this->browse(function (Browser $browser) use ($user, $pasajeroContract, $diaContract): void {
        $browser->loginAs($user)
            ->visit('/services/create')
            ->waitForText('Crear Servicio')
            ->assertSee('Cantidad (unidades del contrato)')
            ->click('#contract_id')
            ->waitFor('[role="listbox"]')
            ->clickAtXPath("//*[@role='option'][contains(., '{$pasajeroContract->contract_number}')]")
            ->waitForText('Cantidad (pasajeros)')
            ->assertSee('Cantidad (pasajeros)')
            ->assertSee('factura por pasajero')
            ->screenshot('service-form-cantidad-pasajeros')
            ->click('#contract_id')
            ->waitFor('[role="listbox"]')
            ->clickAtXPath("//*[@role='option'][contains(., '{$diaContract->contract_number}')]")
            ->waitForText('Cantidad (días)')
            ->assertSee('Cantidad (días)')
            ->assertSee('factura por dia')
            ->screenshot('service-form-cantidad-dias');
    });
});

test('contract create form exposes Unidad de Facturación select', function (): void {
    $user = billingUnitLabelAuthenticateAsSuperAdmin();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->visit('/contracts/create')
            ->waitForText('Crear Contrato')
            ->assertSee('Unidad de Facturación')
            ->assertPresent('#billing_unit_type')
            ->screenshot('contract-form-billing-unit-select');
    });
});
