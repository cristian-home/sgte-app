<?php

use App\Models\Invoice;
use App\Models\ThirdParty;
use App\Models\User;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function (): void {
    $this->artisan('migrate:fresh', ['--no-interaction' => true]);
});

function pdfAuthenticateAsSuperAdmin(): User
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

function pdfUserWithRole(string $roleName): User
{
    $role = SpatieRole::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

test('admin sees the Descargar PDF button on the invoice show page', function (): void {
    $user = pdfAuthenticateAsSuperAdmin();

    $customer = ThirdParty::factory()->create([
        'is_customer' => true,
        'is_natural_person' => false,
        'company_name' => 'Cliente PDF Dusk S.A.',
    ]);
    $invoice = Invoice::factory()->create([
        'third_party_id' => $customer->id,
        'invoice_number' => 'FAC-PDF-DUSK-001',
    ]);

    $this->browse(function (Browser $browser) use ($user, $invoice): void {
        $browser->loginAs($user)
            ->visit("/invoices/{$invoice->id}")
            ->waitForText('FAC-PDF-DUSK-001')
            ->assertSee('Descargar PDF')
            ->assertSourceHas('target="_blank"')
            ->assertSourceHas("/invoices/{$invoice->id}/pdf")
            ->screenshot('invoice-pdf-button-visible');
    });
});

test('operator cannot see the Descargar PDF button on the invoice show page', function (): void {
    $user = pdfUserWithRole('operator');

    $customer = ThirdParty::factory()->create([
        'is_customer' => true,
        'is_natural_person' => false,
        'company_name' => 'Cliente PDF Denied S.A.',
    ]);
    $invoice = Invoice::factory()->create([
        'third_party_id' => $customer->id,
        'invoice_number' => 'FAC-PDF-OP-001',
    ]);

    // Operator lacks VIEW_INVOICES entirely — visiting the show URL
    // returns 403 before the page ever renders the button. The
    // assertion that matters for this requirement: 'Descargar PDF'
    // never appears in the DOM for the operator role.
    $this->browse(function (Browser $browser) use ($user, $invoice): void {
        $browser->loginAs($user)
            ->visit("/invoices/{$invoice->id}")
            ->assertSourceMissing('Descargar PDF')
            ->screenshot('invoice-pdf-button-hidden-for-operator');
    });
});
