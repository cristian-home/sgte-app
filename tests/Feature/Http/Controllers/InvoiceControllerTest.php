<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\ThirdParty;
use App\Models\User;
use Illuminate\Support\Carbon;

use function Pest\Laravel\assertSoftDeleted;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function (): void {
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
    $thirdParty = ThirdParty::factory()->create(['is_customer' => true]);
    $invoice_number = fake()->unique()->numerify('FAC-TEST-####');
    $total_value = fake()->randomFloat(2, 100000, 5000000);
    $issue_date = Carbon::parse(fake()->date());
    $payment_status = fake()->randomElement(['pending', 'paid', 'overdue']);
    $notes = fake()->text();

    $response = post(route('invoices.store'), [
        'third_party_id' => $thirdParty->id,
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
    $invoice_number = fake()->unique()->numerify('FAC-UPD-####');
    $total_value = fake()->randomFloat(2, 100000, 5000000);
    $issue_date = Carbon::parse(fake()->date());
    $payment_status = fake()->randomElement(['pending', 'paid', 'overdue']);
    $notes = fake()->text();

    $response = put(route('invoices.update', $invoice), [
        'third_party_id' => $invoice->third_party_id,
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
    expect($payment_status)->toEqual($invoice->payment_status->value);
    expect($notes)->toEqual($invoice->notes);
});

test('destroy deletes and redirects', function (): void {
    $invoice = Invoice::factory()->create();

    $response = delete(route('invoices.destroy', $invoice));

    $response->assertRedirect(route('invoices.index'));

    assertSoftDeleted($invoice);
});

test('index returns paginated payload with third-party relations', function (): void {
    Invoice::query()->delete();
    ThirdParty::query()->delete();

    Invoice::factory()->count(3)->create();

    $response = get(route('invoices.index'));
    $response->assertOk();

    $page = $response->viewData('page');
    $invoices = $page['props']['invoices'];

    expect($invoices)->toHaveKey('data');
    expect($invoices)->toHaveKey('per_page');
    expect($invoices)->toHaveKey('current_page');
    expect($invoices)->toHaveKey('total');
    expect($invoices['data'])->toHaveCount(3);

    foreach ($invoices['data'] as $row) {
        expect($row)->toHaveKey('third_party');
    }
});

test('index passes customer options for the create modal and the combobox filter', function (): void {
    Invoice::query()->delete();
    ThirdParty::query()->delete();

    ThirdParty::factory()->create(['is_customer' => true, 'is_provider' => false]);
    ThirdParty::factory()->create(['is_customer' => true, 'is_provider' => true]);
    ThirdParty::factory()->create(['is_customer' => false, 'is_provider' => true]);

    $response = get(route('invoices.index'));
    $response->assertOk();

    $options = $response->viewData('page')['props']['thirdParties'];
    expect(count($options))->toBe(2);
    foreach ($options as $opt) {
        expect($opt['is_customer'])->toBeTrue();
    }
});

test('index filters by payment_status pending paid and overdue', function (): void {
    Invoice::query()->delete();

    $pending = Invoice::factory()->create(['payment_status' => PaymentStatus::Pending]);
    $paid = Invoice::factory()->create(['payment_status' => PaymentStatus::Paid]);
    $overdue = Invoice::factory()->create(['payment_status' => PaymentStatus::Overdue]);

    foreach ([
        ['pending', $pending->id],
        ['paid', $paid->id],
        ['overdue', $overdue->id],
    ] as [$status, $expectedId]) {
        $response = get(route('invoices.index', ['filter' => ['payment_status' => $status]]));
        $response->assertOk();

        $rows = $response->viewData('page')['props']['invoices']['data'];
        expect($rows)->toHaveCount(1);
        expect($rows[0]['id'])->toBe($expectedId);
    }
});

test('index filters by third_party_id exact', function (): void {
    Invoice::query()->delete();

    $customerA = ThirdParty::factory()->create(['is_customer' => true]);
    $customerB = ThirdParty::factory()->create(['is_customer' => true]);
    $wanted = Invoice::factory()->create(['third_party_id' => $customerA->id]);
    Invoice::factory()->create(['third_party_id' => $customerB->id]);

    $response = get(route('invoices.index', ['filter' => ['third_party_id' => $customerA->id]]));
    $response->assertOk();

    $rows = $response->viewData('page')['props']['invoices']['data'];
    expect($rows)->toHaveCount(1);
    expect($rows[0]['id'])->toBe($wanted->id);
});

test('index defaults to -issue_date sort', function (): void {
    Invoice::query()->delete();

    Invoice::factory()->create(['issue_date' => Carbon::today()->subDays(5)]);
    $latest = Invoice::factory()->create(['issue_date' => Carbon::today()]);
    Invoice::factory()->create(['issue_date' => Carbon::today()->subDays(10)]);

    $response = get(route('invoices.index'));
    $response->assertOk();

    $rows = $response->viewData('page')['props']['invoices']['data'];
    expect($rows[0]['id'])->toBe($latest->id);
});

test('show returns invoice with thirdParty.documentType loaded', function (): void {
    $customer = ThirdParty::factory()->create(['is_customer' => true]);
    $invoice = Invoice::factory()->create(['third_party_id' => $customer->id]);

    $response = get(route('invoices.show', $invoice));
    $response->assertOk();

    $payload = $response->viewData('page')['props']['invoice'];
    expect($payload)->toHaveKey('third_party');
    expect($payload['third_party'])->toHaveKey('document_type');
});

test('show returns recent services ordered by service_date desc', function (): void {
    $invoice = Invoice::factory()->create();
    $vehicle = \App\Models\Vehicle::factory()->create();
    $driver = \App\Models\Driver::factory()->create();
    $contract = \App\Models\Contract::factory()->create();

    foreach (range(1, 7) as $i) {
        Service::factory()->create([
            'invoice_id' => $invoice->id,
            'contract_id' => $contract->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'service_date' => Carbon::today()->subDays($i),
        ]);
    }

    $response = get(route('invoices.show', $invoice));
    $response->assertOk();

    $rows = $response->viewData('page')['props']['recentServices'];
    expect($rows)->toHaveCount(5);
    $dates = array_map(fn ($r) => $r['service_date'], $rows);
    $sorted = $dates;
    rsort($sorted);
    expect($dates)->toBe($sorted);
});

test('show returns empty recent services when the invoice has none', function (): void {
    $invoice = Invoice::factory()->create();

    $response = get(route('invoices.show', $invoice));
    $response->assertOk();

    $rows = $response->viewData('page')['props']['recentServices'];
    expect($rows)->toBeArray()->toBeEmpty();
});

test('store rejects null third_party_id', function (): void {
    $response = post(route('invoices.store'), [
        'invoice_number' => 'FAC-NULL-TP',
        'total_value' => 50000,
        'issue_date' => Carbon::today()->toDateString(),
        'payment_status' => 'pending',
    ]);

    $response->assertSessionHasErrors(['third_party_id']);
});

test('store rejects zero total_value', function (): void {
    $customer = ThirdParty::factory()->create(['is_customer' => true]);

    $response = post(route('invoices.store'), [
        'third_party_id' => $customer->id,
        'invoice_number' => 'FAC-ZERO-VAL',
        'total_value' => 0,
        'issue_date' => Carbon::today()->toDateString(),
        'payment_status' => 'pending',
    ]);

    $response->assertSessionHasErrors(['total_value']);
});

test('store rejects negative total_value', function (): void {
    $customer = ThirdParty::factory()->create(['is_customer' => true]);

    $response = post(route('invoices.store'), [
        'third_party_id' => $customer->id,
        'invoice_number' => 'FAC-NEG-VAL',
        'total_value' => -1000,
        'issue_date' => Carbon::today()->toDateString(),
        'payment_status' => 'pending',
    ]);

    $response->assertSessionHasErrors(['total_value']);
});

test('store accepts total_value of 0.01', function (): void {
    $customer = ThirdParty::factory()->create(['is_customer' => true]);

    $response = post(route('invoices.store'), [
        'third_party_id' => $customer->id,
        'invoice_number' => 'FAC-MIN-VAL',
        'total_value' => 0.01,
        'issue_date' => Carbon::today()->toDateString(),
        'payment_status' => 'pending',
    ]);

    $response->assertRedirect(route('invoices.index'));
    expect(Invoice::query()->where('invoice_number', 'FAC-MIN-VAL')->count())->toBe(1);
});

test('update rejects null third_party_id', function (): void {
    $invoice = Invoice::factory()->create();

    $response = put(route('invoices.update', $invoice), [
        'invoice_number' => $invoice->invoice_number,
        'total_value' => 50000,
        'issue_date' => Carbon::today()->toDateString(),
        'payment_status' => 'pending',
    ]);

    $response->assertSessionHasErrors(['third_party_id']);
});

test('update allows keeping the same invoice_number', function (): void {
    $invoice = Invoice::factory()->create();

    $response = put(route('invoices.update', $invoice), [
        'third_party_id' => $invoice->third_party_id,
        'invoice_number' => $invoice->invoice_number,
        'total_value' => 75000,
        'issue_date' => Carbon::today()->toDateString(),
        'payment_status' => 'paid',
    ]);

    $response->assertRedirect(route('invoices.index'));
    $invoice->refresh();
    expect((float) $invoice->total_value)->toBe(75000.0);
});

test('markPaid transitions pending invoices to paid', function (): void {
    $invoice = Invoice::factory()->create(['payment_status' => PaymentStatus::Pending]);

    $response = post(route('invoices.mark-paid', $invoice));

    $response->assertRedirect(route('invoices.show', $invoice));
    $invoice->refresh();
    expect($invoice->payment_status)->toBe(PaymentStatus::Paid);
});

test('markPaid rejects already-paid invoices with 422', function (): void {
    $invoice = Invoice::factory()->create(['payment_status' => PaymentStatus::Paid]);

    $response = post(route('invoices.mark-paid', $invoice));

    $response->assertSessionHasErrors(['payment_status']);
    $invoice->refresh();
    expect($invoice->payment_status)->toBe(PaymentStatus::Paid);
});

test('markPaid rejects overdue invoices with 422', function (): void {
    $invoice = Invoice::factory()->create(['payment_status' => PaymentStatus::Overdue]);

    $response = post(route('invoices.mark-paid', $invoice));

    $response->assertSessionHasErrors(['payment_status']);
});

test('operator cannot view invoices', function (): void {
    $operator = User::factory()->create();
    $operator->assignRole('operator');
    $this->actingAs($operator);

    get(route('invoices.index'))->assertForbidden();
});

test('driver cannot view invoices', function (): void {
    $driver = User::factory()->create();
    $driver->assignRole('driver');
    $this->actingAs($driver);

    get(route('invoices.index'))->assertForbidden();
});

test('accounting can mark invoices as paid', function (): void {
    $accounting = User::factory()->create();
    $accounting->assignRole('accounting');
    $this->actingAs($accounting);

    $invoice = Invoice::factory()->create(['payment_status' => PaymentStatus::Pending]);

    $response = post(route('invoices.mark-paid', $invoice));

    $response->assertRedirect(route('invoices.show', $invoice));
    $invoice->refresh();
    expect($invoice->payment_status)->toBe(PaymentStatus::Paid);
});

test('accounting cannot delete invoices', function (): void {
    $accounting = User::factory()->create();
    $accounting->assignRole('accounting');
    $this->actingAs($accounting);

    $invoice = Invoice::factory()->create();

    delete(route('invoices.destroy', $invoice))->assertForbidden();
});
