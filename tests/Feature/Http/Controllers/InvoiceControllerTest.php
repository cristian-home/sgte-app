<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Enums\ServiceStatus;
use App\Models\Contract;
use App\Models\IncidentType;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\ServiceIncident;
use App\Models\ThirdParty;
use App\Models\User;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

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
        ->where('payment_status', $payment_status)
        ->where('notes', $notes)
        ->get();
    expect($invoices)->toHaveCount(1);
    expect($invoices->first()->issue_date)->toBe($issue_date->format('Y-m-d'));
    $invoice = $invoices->first();

    $response->assertRedirect();
    $response->assertSessionHas('success');
});

test('show behaves as expected', function (): void {
    $invoice = Invoice::factory()->create();

    $response = get(route('invoices.show', $invoice));

    $response->assertOk();
});

test('show passes customer options needed by the edit modal', function (): void {
    $invoice = Invoice::factory()->create();

    $response = get(route('invoices.show', $invoice));

    $response->assertOk();
    $response->assertInertia(
        fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->has('thirdParties')
    );
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

    $response->assertRedirect();
    $response->assertSessionHas('success');

    expect($invoice_number)->toEqual($invoice->invoice_number);
    expect($total_value)->toEqual($invoice->total_value);
    expect($issue_date->format('Y-m-d'))->toEqual($invoice->issue_date);
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

    $response->assertRedirect();
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

    $response->assertRedirect();
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

// ================================================================
// Phase 4 billing workflow — attach/detach/recompute + total lock
// ================================================================

test('attach: admin can attach closed services and total recomputes', function (): void {
    $customer = ThirdParty::factory()->create(['is_customer' => true]);
    $contract = Contract::factory()->create(['third_party_id' => $customer->id]);
    $invoice = Invoice::factory()->create([
        'third_party_id' => $customer->id,
        'total_value' => 0,
    ]);
    $s1 = Service::factory()->create([
        'contract_id' => $contract->id,
        'service_status' => ServiceStatus::Closed,
        'unit_value' => 1000,
        'quantity' => 2,
        'invoice_id' => null,
    ]);
    $s2 = Service::factory()->create([
        'contract_id' => $contract->id,
        'service_status' => ServiceStatus::Closed,
        'unit_value' => 500,
        'quantity' => 1,
        'invoice_id' => null,
    ]);

    $response = post(route('invoices.services.attach', $invoice), [
        'service_ids' => [$s1->id, $s2->id],
    ]);

    $response->assertRedirect(route('invoices.show', $invoice));
    expect($s1->fresh()->invoice_id)->toBe($invoice->id);
    expect($s2->fresh()->invoice_id)->toBe($invoice->id);
    expect($invoice->fresh()->total_value)->toBe('2500.00');
});

test('attach: rejects services from a different customer', function (): void {
    $customerA = ThirdParty::factory()->create(['is_customer' => true]);
    $customerB = ThirdParty::factory()->create(['is_customer' => true]);
    $contractB = Contract::factory()->create(['third_party_id' => $customerB->id]);
    $invoice = Invoice::factory()->create(['third_party_id' => $customerA->id]);
    $service = Service::factory()->create([
        'contract_id' => $contractB->id,
        'service_status' => ServiceStatus::Closed,
        'invoice_id' => null,
    ]);

    $response = post(route('invoices.services.attach', $invoice), [
        'service_ids' => [$service->id],
    ]);

    $response->assertSessionHasErrors('service_ids');
    expect($service->fresh()->invoice_id)->toBeNull();
});

test('attach: rejects services already attached to another invoice', function (): void {
    $customer = ThirdParty::factory()->create(['is_customer' => true]);
    $contract = Contract::factory()->create(['third_party_id' => $customer->id]);
    $otherInvoice = Invoice::factory()->create(['third_party_id' => $customer->id]);
    $invoice = Invoice::factory()->create(['third_party_id' => $customer->id]);
    $service = Service::factory()->create([
        'contract_id' => $contract->id,
        'service_status' => ServiceStatus::Closed,
        'invoice_id' => $otherInvoice->id,
    ]);

    $response = post(route('invoices.services.attach', $invoice), [
        'service_ids' => [$service->id],
    ]);

    $response->assertSessionHasErrors('service_ids');
    expect($service->fresh()->invoice_id)->toBe($otherInvoice->id);
});

test('attach: is idempotent for services already attached to this invoice', function (): void {
    $customer = ThirdParty::factory()->create(['is_customer' => true]);
    $contract = Contract::factory()->create(['third_party_id' => $customer->id]);
    $invoice = Invoice::factory()->create(['third_party_id' => $customer->id]);
    $service = Service::factory()->create([
        'contract_id' => $contract->id,
        'service_status' => ServiceStatus::Closed,
        'unit_value' => 1000,
        'quantity' => 1,
        'invoice_id' => $invoice->id,
    ]);

    $response = post(route('invoices.services.attach', $invoice), [
        'service_ids' => [$service->id],
    ]);

    $response->assertRedirect();
    expect($service->fresh()->invoice_id)->toBe($invoice->id);
});

test('attach: rejects open-status services', function (): void {
    $customer = ThirdParty::factory()->create(['is_customer' => true]);
    $contract = Contract::factory()->create(['third_party_id' => $customer->id]);
    $invoice = Invoice::factory()->create(['third_party_id' => $customer->id]);
    $service = Service::factory()->create([
        'contract_id' => $contract->id,
        'service_status' => ServiceStatus::Open,
        'invoice_id' => null,
    ]);

    $response = post(route('invoices.services.attach', $invoice), [
        'service_ids' => [$service->id],
    ]);

    $response->assertSessionHasErrors('service_ids');
    expect($service->fresh()->invoice_id)->toBeNull();
});

test('attach: rejects empty service_ids array', function (): void {
    $invoice = Invoice::factory()->create();

    $response = post(route('invoices.services.attach', $invoice), [
        'service_ids' => [],
    ]);

    $response->assertSessionHasErrors('service_ids');
});

test('detach: admin can detach a service and total recomputes', function (): void {
    $customer = ThirdParty::factory()->create(['is_customer' => true]);
    $contract = Contract::factory()->create(['third_party_id' => $customer->id]);
    $invoice = Invoice::factory()->create(['third_party_id' => $customer->id]);
    $s1 = Service::factory()->create([
        'contract_id' => $contract->id,
        'service_status' => ServiceStatus::Closed,
        'invoice_id' => $invoice->id,
        'unit_value' => 1000,
        'quantity' => 2,
    ]);
    $s2 = Service::factory()->create([
        'contract_id' => $contract->id,
        'service_status' => ServiceStatus::Closed,
        'invoice_id' => $invoice->id,
        'unit_value' => 500,
        'quantity' => 1,
    ]);

    $response = delete(route('invoices.services.detach', [$invoice, $s2]));

    $response->assertRedirect(route('invoices.show', $invoice));
    expect($s2->fresh()->invoice_id)->toBeNull();
    expect($s1->fresh()->invoice_id)->toBe($invoice->id);
    expect($invoice->fresh()->total_value)->toBe('2000.00');
});

test('detach: returns 404 when service is not attached to the invoice', function (): void {
    $customer = ThirdParty::factory()->create(['is_customer' => true]);
    $invoice = Invoice::factory()->create(['third_party_id' => $customer->id]);
    $otherInvoice = Invoice::factory()->create(['third_party_id' => $customer->id]);
    $service = Service::factory()->create([
        'invoice_id' => $otherInvoice->id,
    ]);

    $response = delete(route('invoices.services.detach', [$invoice, $service]));

    $response->assertNotFound();
});

test('recompute: endpoint updates total when upstream values change', function (): void {
    $customer = ThirdParty::factory()->create(['is_customer' => true]);
    $contract = Contract::factory()->create(['third_party_id' => $customer->id]);
    $invoice = Invoice::factory()->create(['third_party_id' => $customer->id, 'total_value' => 1000]);
    $service = Service::factory()->create([
        'contract_id' => $contract->id,
        'service_status' => ServiceStatus::Closed,
        'invoice_id' => $invoice->id,
        'unit_value' => 1000,
        'quantity' => 1,
    ]);

    // Simulate an upstream change: unit_value doubles.
    $service->update(['unit_value' => 2000]);

    // invoice.total_value is now stale (still 1000).
    expect($invoice->fresh()->total_value)->toBe('1000.00');

    $response = post(route('invoices.recompute-total', $invoice));

    $response->assertRedirect(route('invoices.show', $invoice));
    expect($invoice->fresh()->total_value)->toBe('2000.00');
});

test('recompute: picks up new billing-affecting incidents', function (): void {
    $customer = ThirdParty::factory()->create(['is_customer' => true]);
    $contract = Contract::factory()->create(['third_party_id' => $customer->id]);
    $invoice = Invoice::factory()->create(['third_party_id' => $customer->id, 'total_value' => 1000]);
    $service = Service::factory()->create([
        'contract_id' => $contract->id,
        'service_status' => ServiceStatus::Closed,
        'invoice_id' => $invoice->id,
        'unit_value' => 1000,
        'quantity' => 1,
    ]);

    // Add an affects_billing incident after attach.
    \App\Models\ServiceIncident::factory()->create([
        'service_id' => $service->id,
        'incident_type_id' => \App\Models\IncidentType::factory()->create()->id,
        'affects_billing' => true,
        'additional_value' => 500,
    ]);

    $response = post(route('invoices.recompute-total', $invoice));

    $response->assertRedirect();
    expect($invoice->fresh()->total_value)->toBe('1500.00');
});

test('update: rejects total_value changes when services are attached', function (): void {
    $customer = ThirdParty::factory()->create(['is_customer' => true]);
    $contract = Contract::factory()->create(['third_party_id' => $customer->id]);
    $invoice = Invoice::factory()->create(['third_party_id' => $customer->id, 'total_value' => 2500]);
    Service::factory()->create([
        'contract_id' => $contract->id,
        'invoice_id' => $invoice->id,
        'service_status' => ServiceStatus::Closed,
    ]);

    $response = put(route('invoices.update', $invoice), [
        'third_party_id' => $customer->id,
        'invoice_number' => $invoice->invoice_number,
        'total_value' => 9999,
        'issue_date' => $invoice->issue_date,
        'payment_status' => PaymentStatus::Pending->value,
        'notes' => 'attempt to change',
    ]);

    $response->assertSessionHasErrors('total_value');
});

test('update: allows other field changes when services are attached if total_value unchanged', function (): void {
    $customer = ThirdParty::factory()->create(['is_customer' => true]);
    $contract = Contract::factory()->create(['third_party_id' => $customer->id]);
    $invoice = Invoice::factory()->create(['third_party_id' => $customer->id, 'total_value' => 2500]);
    Service::factory()->create([
        'contract_id' => $contract->id,
        'invoice_id' => $invoice->id,
        'service_status' => ServiceStatus::Closed,
    ]);

    $response = put(route('invoices.update', $invoice), [
        'third_party_id' => $customer->id,
        'invoice_number' => $invoice->invoice_number,
        'total_value' => 2500,
        'issue_date' => $invoice->issue_date,
        'payment_status' => PaymentStatus::Pending->value,
        'notes' => 'new notes',
    ]);

    $response->assertRedirect();
    expect($invoice->fresh()->notes)->toBe('new notes');
});

test('update: allows total_value changes when no services are attached', function (): void {
    $customer = ThirdParty::factory()->create(['is_customer' => true]);
    $invoice = Invoice::factory()->create(['third_party_id' => $customer->id, 'total_value' => 1000]);

    $response = put(route('invoices.update', $invoice), [
        'third_party_id' => $customer->id,
        'invoice_number' => $invoice->invoice_number,
        'total_value' => 5000,
        'issue_date' => $invoice->issue_date,
        'payment_status' => PaymentStatus::Pending->value,
    ]);

    $response->assertRedirect();
    expect((float) $invoice->fresh()->total_value)->toBe(5000.0);
});

test('attach: operator receives 403', function (): void {
    $operator = User::factory()->create();
    $operator->assignRole('operator');
    $this->actingAs($operator);

    $invoice = Invoice::factory()->create();

    post(route('invoices.services.attach', $invoice), [
        'service_ids' => [1],
    ])->assertForbidden();
});

test('detach: operator receives 403', function (): void {
    $operator = User::factory()->create();
    $operator->assignRole('operator');
    $this->actingAs($operator);

    $invoice = Invoice::factory()->create();
    $service = Service::factory()->create(['invoice_id' => $invoice->id]);

    delete(route('invoices.services.detach', [$invoice, $service]))->assertForbidden();
});

test('recompute: operator receives 403', function (): void {
    $operator = User::factory()->create();
    $operator->assignRole('operator');
    $this->actingAs($operator);

    $invoice = Invoice::factory()->create();

    post(route('invoices.recompute-total', $invoice))->assertForbidden();
});

test('attach: accounting can attach services', function (): void {
    $accounting = User::factory()->create();
    $accounting->assignRole('accounting');
    $this->actingAs($accounting);

    $customer = ThirdParty::factory()->create(['is_customer' => true]);
    $contract = Contract::factory()->create(['third_party_id' => $customer->id]);
    $invoice = Invoice::factory()->create(['third_party_id' => $customer->id]);
    $service = Service::factory()->create([
        'contract_id' => $contract->id,
        'service_status' => ServiceStatus::Closed,
        'invoice_id' => null,
        'unit_value' => 1000,
        'quantity' => 1,
    ]);

    $response = post(route('invoices.services.attach', $invoice), [
        'service_ids' => [$service->id],
    ]);

    $response->assertRedirect(route('invoices.show', $invoice));
    expect($service->fresh()->invoice_id)->toBe($invoice->id);
});

test('show: includes computedTotal, services_count, and candidateServices props', function (): void {
    $customer = ThirdParty::factory()->create(['is_customer' => true]);
    $contract = Contract::factory()->create(['third_party_id' => $customer->id]);
    $invoice = Invoice::factory()->create(['third_party_id' => $customer->id]);
    Service::factory()->create([
        'contract_id' => $contract->id,
        'invoice_id' => $invoice->id,
        'service_status' => ServiceStatus::Closed,
        'unit_value' => 1000,
        'quantity' => 3,
    ]);
    // Also a candidate
    Service::factory()->create([
        'contract_id' => $contract->id,
        'invoice_id' => null,
        'service_status' => ServiceStatus::Closed,
        'service_date' => now()->subDays(5),
    ]);

    $response = get(route('invoices.show', $invoice));
    $response->assertOk();

    $props = $response->viewData('page')['props'];
    expect($props)->toHaveKey('computedTotal');
    expect($props['computedTotal'])->toBe('3000.00');
    expect($props['invoice']['services_count'])->toBe(1);
    expect($props['candidateServices'])->toHaveCount(1);
});

test('show: candidateServices excludes open-status services', function (): void {
    $customer = ThirdParty::factory()->create(['is_customer' => true]);
    $contract = Contract::factory()->create(['third_party_id' => $customer->id]);
    $invoice = Invoice::factory()->create(['third_party_id' => $customer->id]);
    Service::factory()->create([
        'contract_id' => $contract->id,
        'invoice_id' => null,
        'service_status' => ServiceStatus::Closed,
        'service_date' => now()->subDays(5),
    ]);
    Service::factory()->create([
        'contract_id' => $contract->id,
        'invoice_id' => null,
        'service_status' => ServiceStatus::Open,
        'service_date' => now()->subDays(5),
    ]);

    $response = get(route('invoices.show', $invoice));
    $props = $response->viewData('page')['props'];
    expect($props['candidateServices'])->toHaveCount(1);
});

test('show: candidateServices excludes services from other customers', function (): void {
    $customerA = ThirdParty::factory()->create(['is_customer' => true]);
    $customerB = ThirdParty::factory()->create(['is_customer' => true]);
    $contractA = Contract::factory()->create(['third_party_id' => $customerA->id]);
    $contractB = Contract::factory()->create(['third_party_id' => $customerB->id]);
    $invoice = Invoice::factory()->create(['third_party_id' => $customerA->id]);

    Service::factory()->create([
        'contract_id' => $contractA->id,
        'invoice_id' => null,
        'service_status' => ServiceStatus::Closed,
        'service_date' => now()->subDays(5),
    ]);
    Service::factory()->create([
        'contract_id' => $contractB->id,
        'invoice_id' => null,
        'service_status' => ServiceStatus::Closed,
        'service_date' => now()->subDays(5),
    ]);

    $response = get(route('invoices.show', $invoice));
    $props = $response->viewData('page')['props'];
    expect($props['candidateServices'])->toHaveCount(1);
});

test('show: candidateServices respects the 90-day window', function (): void {
    $customer = ThirdParty::factory()->create(['is_customer' => true]);
    $contract = Contract::factory()->create(['third_party_id' => $customer->id]);
    $invoice = Invoice::factory()->create(['third_party_id' => $customer->id]);

    Service::factory()->create([
        'contract_id' => $contract->id,
        'invoice_id' => null,
        'service_status' => ServiceStatus::Closed,
        'service_date' => now()->subDays(5),
    ]);
    Service::factory()->create([
        'contract_id' => $contract->id,
        'invoice_id' => null,
        'service_status' => ServiceStatus::Closed,
        'service_date' => now()->subDays(95),
    ]);

    $response = get(route('invoices.show', $invoice));
    $props = $response->viewData('page')['props'];
    expect($props['candidateServices'])->toHaveCount(1);
});

// ================================================================
// Invoice PDF generation
// ================================================================

test('pdf: admin can download the invoice PDF inline', function (): void {
    $customer = ThirdParty::factory()->create([
        'is_customer' => true,
        'is_natural_person' => false,
        'company_name' => 'Cliente PDF Prueba S.A.',
    ]);
    $contract = Contract::factory()->create(['third_party_id' => $customer->id]);
    $invoice = Invoice::factory()->create([
        'third_party_id' => $customer->id,
        'invoice_number' => 'FAC-PDF-TEST-001',
    ]);
    Service::factory()->create([
        'contract_id' => $contract->id,
        'service_status' => ServiceStatus::Closed,
        'invoice_id' => $invoice->id,
        'unit_value' => 100000,
        'quantity' => 1,
    ]);

    $response = get(route('invoices.pdf', $invoice));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toStartWith('application/pdf');
    expect($response->headers->get('Content-Disposition'))->toContain('filename=factura-FAC-PDF-TEST-001.pdf');
    expect($response->getContent())->toStartWith('%PDF-');
});

test('pdf: accounting user can download the invoice PDF', function (): void {
    $accounting = User::factory()->create();
    $accounting->assignRole('accounting');
    $this->actingAs($accounting);

    $invoice = Invoice::factory()->create();

    $response = get(route('invoices.pdf', $invoice));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toStartWith('application/pdf');
});

test('pdf: operator receives 403 on the invoice PDF endpoint', function (): void {
    $operator = User::factory()->create();
    $operator->assignRole('operator');
    $this->actingAs($operator);

    $invoice = Invoice::factory()->create();

    get(route('invoices.pdf', $invoice))->assertForbidden();
});

test('pdf: driver receives 403 on the invoice PDF endpoint', function (): void {
    $driver = User::factory()->create();
    $driver->assignRole('driver');
    $this->actingAs($driver);

    $invoice = Invoice::factory()->create();

    get(route('invoices.pdf', $invoice))->assertForbidden();
});

test('pdf: unauthenticated user is redirected to login', function (): void {
    auth()->logout();

    $invoice = Invoice::factory()->create();

    $response = $this->get(route('invoices.pdf', $invoice));
    $response->assertRedirect(route('login'));
});

test('pdf: regenerates invoice total from the calculator on every request', function (): void {
    $customer = ThirdParty::factory()->create(['is_customer' => true]);
    $contract = Contract::factory()->create(['third_party_id' => $customer->id]);
    $invoice = Invoice::factory()->create([
        'third_party_id' => $customer->id,
        'total_value' => 9999,
    ]);
    Service::factory()->create([
        'contract_id' => $contract->id,
        'service_status' => ServiceStatus::Closed,
        'invoice_id' => $invoice->id,
        'unit_value' => 1000,
        'quantity' => 1,
    ]);

    // Force the stale total back in place after the factory/calculator
    // side-effects settle — simulate drift from an upstream field change.
    \App\Models\Invoice::query()->where('id', $invoice->id)->update(['total_value' => 9999]);
    expect($invoice->fresh()->total_value)->toBe('9999.00');

    get(route('invoices.pdf', $invoice))->assertOk();

    expect($invoice->fresh()->total_value)->toBe('1000.00');
});

test('pdf: response Content-Disposition starts with inline', function (): void {
    $invoice = Invoice::factory()->create();

    $response = get(route('invoices.pdf', $invoice));

    expect($response->headers->get('Content-Disposition'))->toStartWith('inline');
});

test('pdf: rendered HTML content includes key invoice fields', function (): void {
    $customer = ThirdParty::factory()->create([
        'is_customer' => true,
        'is_natural_person' => false,
        'company_name' => 'Cliente PDF Smoke S.A.',
    ]);
    $invoice = Invoice::factory()->create([
        'third_party_id' => $customer->id,
        'invoice_number' => 'FAC-SMOKE-002',
    ]);

    // Render the Blade view directly to assert text presence — more
    // reliable than grepping the dompdf-compressed binary output.
    $calculator = new \App\Services\InvoiceTotalCalculator;
    $calculator->recomputeFor($invoice->fresh());
    $invoice = $invoice->fresh()->load([
        'thirdParty.documentType',
        'thirdParty.municipality.department',
        'services',
        'services.vehicle:id,plate',
        'services.contract:id,contract_number',
        'services.serviceIncidents' => fn ($q) => $q->where('affects_billing', true),
        'services.serviceIncidents.incidentType:id,name',
    ]);
    $services = $invoice->services;
    $billingIncidents = $services->flatMap(fn ($s) => $s->serviceIncidents)->values();
    $subtotalServices = (float) $services->sum(fn ($s) => (float) $s->unit_value * (int) $s->quantity);
    $subtotalIncidents = (float) $billingIncidents->sum(fn ($i) => (float) ($i->additional_value ?? 0));

    $html = view('invoices.pdf', [
        'invoice' => $invoice,
        'services' => $services,
        'billing_incidents' => $billingIncidents,
        'subtotal_services' => $subtotalServices,
        'subtotal_incidents' => $subtotalIncidents,
        'grand_total' => $subtotalServices + $subtotalIncidents,
        'customer_name' => 'Cliente PDF Smoke S.A.',
        'customer_document' => 'NIT 900000001',
        'customer_address_line' => '',
        'now_formatted' => 'domingo, 18 de abril de 2026 12:00',
    ])->render();

    expect($html)->toContain('FAC-SMOKE-002');
    expect($html)->toContain('Cliente PDF Smoke S.A.');
    expect($html)->toContain('INFORMATIVO');
    expect($html)->toContain('no constituye factura fiscal');
});

test('pdf: zero-services invoice renders the manual-total fallback note', function (): void {
    $customer = ThirdParty::factory()->create(['is_customer' => true]);
    $invoice = Invoice::factory()->create([
        'third_party_id' => $customer->id,
        'invoice_number' => 'FAC-MANUAL-003',
        'total_value' => 750000,
    ]);

    $html = view('invoices.pdf', [
        'invoice' => $invoice->fresh(),
        'services' => collect(),
        'billing_incidents' => collect(),
        'subtotal_services' => 0.0,
        'subtotal_incidents' => 0.0,
        'grand_total' => 750000.0,
        'customer_name' => 'Test',
        'customer_document' => '',
        'customer_address_line' => '',
        'now_formatted' => 'ahora',
    ])->render();

    expect($html)->toContain('Sin servicios asociados — valor total manual.');
});

// ================================================================
// REQ-011 billing-gate: affects_billing=true incidents block attach
// (invoice-billing-blocked-incidents requirement)
// ================================================================

function billingBlockedScenario(): array
{
    $customer = ThirdParty::factory()->create(['is_customer' => true]);
    $contract = Contract::factory()->create(['third_party_id' => $customer->id]);
    $invoice = Invoice::factory()->create([
        'third_party_id' => $customer->id,
        'total_value' => 0,
    ]);
    $service = Service::factory()->create([
        'contract_id' => $contract->id,
        'service_status' => ServiceStatus::Closed,
        'unit_value' => 1000,
        'quantity' => 2,
        'invoice_id' => null,
    ]);
    $incidentType = IncidentType::factory()->create([
        'name' => 'Ruta truncada',
        'affects_billing_default' => true,
    ]);
    ServiceIncident::factory()->create([
        'service_id' => $service->id,
        'incident_type_id' => $incidentType->id,
        'affects_billing' => true,
        'additional_value' => -200,
    ]);

    return [$invoice, $service];
}

test('attach: rejects services with billing-affecting incidents when no override_justification', function (): void {
    [$invoice, $service] = billingBlockedScenario();

    $response = post(route('invoices.services.attach', $invoice), [
        'service_ids' => [$service->id],
    ]);

    $response->assertSessionHasErrors('service_ids');
    $errors = session('errors')->get('service_ids');
    expect($errors)->not->toBeEmpty();
    expect(implode(' ', $errors))
        ->toContain('novedades que afectan la facturación')
        ->toContain('Ruta truncada');
    expect($service->fresh()->invoice_id)->toBeNull();
});

test('attach: accepts services with billing-affecting incidents when override_justification is present', function (): void {
    [$invoice, $service] = billingBlockedScenario();

    $response = post(route('invoices.services.attach', $invoice), [
        'service_ids' => [$service->id],
        'override_justification' => 'Cliente aceptó cobrar el servicio a pesar de la ruta truncada.',
    ]);

    $response->assertRedirect(route('invoices.show', $invoice));
    expect($service->fresh()->invoice_id)->toBe($invoice->id);
});

test('attach: override_justification must meet minimum length', function (): void {
    [$invoice, $service] = billingBlockedScenario();

    $response = post(route('invoices.services.attach', $invoice), [
        'service_ids' => [$service->id],
        'override_justification' => 'corta',
    ]);

    $response->assertSessionHasErrors('override_justification');
    expect($service->fresh()->invoice_id)->toBeNull();
});

test('attach: override logs activity_log entry with justification + overridden_service_ids', function (): void {
    [$invoice, $service] = billingBlockedScenario();

    $justification = 'El cliente autorizó la facturación del servicio tras revisar la novedad.';

    post(route('invoices.services.attach', $invoice), [
        'service_ids' => [$service->id],
        'override_justification' => $justification,
    ])->assertRedirect();

    $activity = Activity::query()
        ->where('subject_type', Invoice::class)
        ->where('subject_id', $invoice->id)
        ->where('description', 'Servicios con novedades facturables asociados con justificación')
        ->latest()
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties->get('override_justification'))->toBe($justification);
    expect($activity->properties->get('overridden_service_ids'))->toContain($service->id);
    expect($activity->properties->get('attached_service_ids'))->toContain($service->id);
});

test('attach: clean service (no billing-affecting incident) still attaches without a justification', function (): void {
    $customer = ThirdParty::factory()->create(['is_customer' => true]);
    $contract = Contract::factory()->create(['third_party_id' => $customer->id]);
    $invoice = Invoice::factory()->create(['third_party_id' => $customer->id, 'total_value' => 0]);
    $service = Service::factory()->create([
        'contract_id' => $contract->id,
        'service_status' => ServiceStatus::Closed,
        'invoice_id' => null,
    ]);
    // Non-billing-affecting incident — shouldn't block.
    $incidentType = IncidentType::factory()->create(['affects_billing_default' => false]);
    ServiceIncident::factory()->create([
        'service_id' => $service->id,
        'incident_type_id' => $incidentType->id,
        'affects_billing' => false,
        'additional_value' => null,
    ]);

    $response = post(route('invoices.services.attach', $invoice), [
        'service_ids' => [$service->id],
    ]);

    $response->assertRedirect(route('invoices.show', $invoice));
    expect($service->fresh()->invoice_id)->toBe($invoice->id);
});

test('attach: mixed batch (one clean + one blocked) rejects without justification', function (): void {
    [$invoice] = billingBlockedScenario();
    $contract = Contract::factory()->create(['third_party_id' => $invoice->third_party_id]);
    $cleanService = Service::factory()->create([
        'contract_id' => $contract->id,
        'service_status' => ServiceStatus::Closed,
        'invoice_id' => null,
    ]);
    $blockedService = Service::query()->where('invoice_id', null)
        ->where('id', '!=', $cleanService->id)
        ->first();

    $response = post(route('invoices.services.attach', $invoice), [
        'service_ids' => [$cleanService->id, $blockedService->id],
    ]);

    $response->assertSessionHasErrors('service_ids');
    expect($cleanService->fresh()->invoice_id)->toBeNull();
    expect($blockedService->fresh()->invoice_id)->toBeNull();
});

test('show: candidateServices excludes billing-blocked; blockedCandidateServices lists them separately', function (): void {
    $customer = ThirdParty::factory()->create(['is_customer' => true]);
    $contract = Contract::factory()->create(['third_party_id' => $customer->id]);
    $invoice = Invoice::factory()->create(['third_party_id' => $customer->id, 'total_value' => 0]);
    $cleanService = Service::factory()->create([
        'contract_id' => $contract->id,
        'service_status' => ServiceStatus::Closed,
        'invoice_id' => null,
    ]);
    $blockedService = Service::factory()->create([
        'contract_id' => $contract->id,
        'service_status' => ServiceStatus::Closed,
        'invoice_id' => null,
    ]);
    $incidentType = IncidentType::factory()->create(['affects_billing_default' => true]);
    ServiceIncident::factory()->create([
        'service_id' => $blockedService->id,
        'incident_type_id' => $incidentType->id,
        'affects_billing' => true,
    ]);

    $response = get(route('invoices.show', $invoice));

    $response->assertInertia(function ($page) use ($cleanService, $blockedService) {
        $page->has('candidateServices', 1, fn ($row) => $row->where('id', $cleanService->id)->etc());
        $page->has('blockedCandidateServices', 1, fn ($row) => $row->where('id', $blockedService->id)->etc());
    });
});
