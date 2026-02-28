<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role as SpatieRole;

use function Pest\Laravel\assertSoftDeleted;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function (): void {
    SpatieRole::create(['name' => 'super_admin', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);
});

test('index behaves as expected', function (): void {
    $invoices = Invoice::factory()->count(3)->create();

    $response = get(route('invoices.index'));

    $response->assertOk();
});

test('create behaves as expected', function (): void {
    $response = get(route('invoices.create'));

    $response->assertOk();
});

test('store uses form request validation')
    ->assertActionUsesFormRequest(
        \App\Http\Controllers\InvoiceController::class,
        'store',
        \App\Http\Requests\InvoiceStoreRequest::class
    );

test('store saves and redirects', function (): void {
    $invoice_number = fake()->word();
    $total_value = fake()->randomFloat(2, 100000, 5000000);
    $issue_date = Carbon::parse(fake()->date());
    $payment_status = fake()->randomElement(['pending', 'paid', 'overdue']);
    $notes = fake()->text();

    $response = post(route('invoices.store'), [
        'invoice_number' => $invoice_number,
        'total_value' => $total_value,
        'issue_date' => $issue_date,
        'payment_status' => $payment_status,
        'notes' => $notes,
    ]);

    $invoices = Invoice::query()
        ->where('invoice_number', $invoice_number)
        ->where('total_value', $total_value)
        ->where('issue_date', $issue_date)
        ->where('payment_status', $payment_status)
        ->where('notes', $notes)
        ->get();
    expect($invoices)->toHaveCount(1);
    $invoice = $invoices->first();

    $response->assertRedirect(route('invoices.index'));
});

test('show behaves as expected', function (): void {
    $invoice = Invoice::factory()->create();

    $response = get(route('invoices.show', $invoice));

    $response->assertOk();
});

test('edit behaves as expected', function (): void {
    $invoice = Invoice::factory()->create();

    $response = get(route('invoices.edit', $invoice));

    $response->assertOk();
});

test('update uses form request validation')
    ->assertActionUsesFormRequest(
        \App\Http\Controllers\InvoiceController::class,
        'update',
        \App\Http\Requests\InvoiceUpdateRequest::class
    );

test('update redirects', function (): void {
    $invoice = Invoice::factory()->create();
    $invoice_number = fake()->word();
    $total_value = fake()->randomFloat(2, 100000, 5000000);
    $issue_date = Carbon::parse(fake()->date());
    $payment_status = fake()->randomElement(['pending', 'paid', 'overdue']);
    $notes = fake()->text();

    $response = put(route('invoices.update', $invoice), [
        'invoice_number' => $invoice_number,
        'total_value' => $total_value,
        'issue_date' => $issue_date,
        'payment_status' => $payment_status,
        'notes' => $notes,
    ]);

    $invoice->refresh();

    $response->assertRedirect(route('invoices.index'));

    expect($invoice_number)->toEqual($invoice->invoice_number);
    expect($total_value)->toEqual($invoice->total_value);
    expect($issue_date)->toEqual($invoice->issue_date);
    expect($payment_status)->toEqual($invoice->payment_status);
    expect($notes)->toEqual($invoice->notes);
});

test('destroy deletes and redirects', function (): void {
    $invoice = Invoice::factory()->create();

    $response = delete(route('invoices.destroy', $invoice));

    $response->assertRedirect(route('invoices.index'));

    assertSoftDeleted($invoice);
});
