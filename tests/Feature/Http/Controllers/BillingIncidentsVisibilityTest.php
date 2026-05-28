<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\ServiceIncident;
use App\Models\User;
use App\Services\InvoiceTotalCalculator;
use App\Support\Tz;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\get;

/**
 * Cross-controller coverage for the "novedades que afectan facturación"
 * visibility feature. Each test asserts the new prop the controller adds
 * to its Inertia payload AND that the sums agree with
 * {@see InvoiceTotalCalculator} (the source of truth used by the PDF).
 */
beforeEach(function (): void {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);
});

test('service show sends billingIncidents shaped for the breakdown table', function (): void {
    $service = Service::factory()->create([
        'unit_value' => 150000,
        'quantity' => 2,
    ]);

    // Two billing-affecting + one non-billing — only the first two should
    // appear in the breakdown payload.
    ServiceIncident::factory()->create([
        'service_id' => $service->id,
        'affects_billing' => true,
        'additional_value' => 25000,
        'description' => 'Pernocta',
    ]);
    ServiceIncident::factory()->create([
        'service_id' => $service->id,
        'affects_billing' => true,
        'additional_value' => 10000,
        'description' => 'Peajes',
    ]);
    ServiceIncident::factory()->create([
        'service_id' => $service->id,
        'affects_billing' => false,
        'additional_value' => null,
        'description' => 'Trafico',
    ]);

    $response = get(route('services.show', $service));

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('services/show')
        ->has('billingIncidents', 2)
        ->where('billingIncidents.0.description', 'Pernocta')
        ->where('billingIncidents.0.additional_value', '25000.00')
        ->has('billingIncidents.0.incident_type')
    );
});

test('service show omits billing breakdown when no billing-affecting incidents', function (): void {
    $service = Service::factory()->create();
    ServiceIncident::factory()->create([
        'service_id' => $service->id,
        'affects_billing' => false,
        'additional_value' => null,
    ]);

    $response = get(route('services.show', $service));

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('services/show')
        ->has('billingIncidents', 0)
    );
});

test('invoice show sends billingTotals matching InvoiceTotalCalculator', function (): void {
    $invoice = Invoice::factory()->create();
    $contract = Contract::factory()->create(['third_party_id' => $invoice->third_party_id]);

    $serviceA = Service::factory()->create([
        'invoice_id' => $invoice->id,
        'contract_id' => $contract->id,
        'unit_value' => 100000,
        'quantity' => 3,
    ]);
    $serviceB = Service::factory()->create([
        'invoice_id' => $invoice->id,
        'contract_id' => $contract->id,
        'unit_value' => 80000,
        'quantity' => 1,
    ]);

    ServiceIncident::factory()->create([
        'service_id' => $serviceA->id,
        'affects_billing' => true,
        'additional_value' => 15000,
    ]);
    ServiceIncident::factory()->create([
        'service_id' => $serviceB->id,
        'affects_billing' => true,
        'additional_value' => 7500,
    ]);
    ServiceIncident::factory()->create([
        'service_id' => $serviceB->id,
        'affects_billing' => false,
        'additional_value' => 999999,
    ]);

    $response = get(route('invoices.show', $invoice));

    $expectedTotal = (float) app(InvoiceTotalCalculator::class)->computeFor($invoice->fresh());

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('invoices/show')
        // Inertia round-trips floats through JSON, which loses the ".0"
        // for whole values — assert via numeric equality (callback) so
        // either int or float round-trips pass.
        ->where('billingTotals.subtotal_services', fn ($v) => (float) $v === 380000.0)
        ->where('billingTotals.subtotal_incidents', fn ($v) => (float) $v === 22500.0)
        ->where('billingTotals.grand_total', fn ($v) => (float) $v === $expectedTotal)
    );
});

test('invoice show recentServices carry only billing-affecting incidents', function (): void {
    $invoice = Invoice::factory()->create();
    $service = Service::factory()->create([
        'invoice_id' => $invoice->id,
        'unit_value' => 50000,
        'quantity' => 1,
    ]);

    ServiceIncident::factory()->create([
        'service_id' => $service->id,
        'affects_billing' => true,
        'additional_value' => 12000,
    ]);
    ServiceIncident::factory()->create([
        'service_id' => $service->id,
        'affects_billing' => false,
        'additional_value' => null,
    ]);

    $response = get(route('invoices.show', $invoice));

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->has('recentServices', 1)
        ->has('recentServices.0.service_incidents', 1)
        ->where('recentServices.0.service_incidents.0.affects_billing', true)
    );
});

test('day summary aggregates billing impact per service and totals across day', function (): void {
    // Service::saving derives service_date_local from planned_start_at,
    // so we pin the instant explicitly rather than relying on the
    // service_date_local override (factory default is ±1 month random).
    $tz = Tz::operation();
    $today = CarbonImmutable::now($tz)->toDateString();
    $plannedStart = CarbonImmutable::createFromFormat('Y-m-d H:i', "{$today} 08:00", $tz)->utc();

    $service = Service::factory()->create([
        'planned_start_at' => $plannedStart,
        'timezone' => $tz,
    ]);

    ServiceIncident::factory()->create([
        'service_id' => $service->id,
        'affects_billing' => true,
        'additional_value' => 20000,
    ]);
    ServiceIncident::factory()->create([
        'service_id' => $service->id,
        'affects_billing' => true,
        'additional_value' => 5000,
    ]);
    ServiceIncident::factory()->create([
        'service_id' => $service->id,
        'affects_billing' => false,
        'additional_value' => 9999,
    ]);

    $response = get(route('day-summary.index', ['date' => $today]));

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('day-summary/index')
        ->has('services', 1)
        ->where('services.0.billing_incidents_count', 2)
        ->where('services.0.billing_impact_amount', fn ($v) => (float) $v === 25000.0)
        ->where('summary.billing_impact_total', fn ($v) => (float) $v === 25000.0)
    );
});

test('service incidents index sends filtered billing total', function (): void {
    $incidentA = ServiceIncident::factory()->create([
        'affects_billing' => true,
        'additional_value' => 30000,
    ]);
    ServiceIncident::factory()->create([
        'service_id' => $incidentA->service_id,
        'affects_billing' => true,
        'additional_value' => 11500,
    ]);
    ServiceIncident::factory()->create([
        'service_id' => $incidentA->service_id,
        'affects_billing' => false,
        'additional_value' => 9999,
    ]);

    $response = get(route('service-incidents.index'));

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('service-incidents/index')
        ->where('filteredBillingTotal', fn ($v) => (float) $v === 41500.0)
    );
});

test('service incidents index filteredBillingTotal honors the affects_billing filter', function (): void {
    $incident = ServiceIncident::factory()->create([
        'affects_billing' => true,
        'additional_value' => 18000,
    ]);
    ServiceIncident::factory()->create([
        'service_id' => $incident->service_id,
        'affects_billing' => false,
        'additional_value' => 9999,
    ]);

    $response = get(route('service-incidents.index', ['filter[affects_billing]' => 1]));

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('filteredBillingTotal', fn ($v) => (float) $v === 18000.0)
    );
});

test('service incidents JSON endpoint returns filtered_billing_total alongside paginator', function (): void {
    $incident = ServiceIncident::factory()->create([
        'affects_billing' => true,
        'additional_value' => 22000,
    ]);
    ServiceIncident::factory()->create([
        'service_id' => $incident->service_id,
        'affects_billing' => true,
        'additional_value' => 3000,
    ]);
    ServiceIncident::factory()->create([
        'service_id' => $incident->service_id,
        'affects_billing' => false,
        'additional_value' => 9999,
    ]);

    // useServerTable refetches via fetch(... Accept: application/json),
    // not Inertia partial-reload. The JSON shape must preserve the
    // paginator keys AND expose the filter-aware total so the page can
    // update its footer in lockstep with the filter UI.
    $unfiltered = $this->getJson(route('service-incidents.index'));
    $unfiltered->assertOk()
        ->assertJsonStructure(['data', 'current_page', 'last_page', 'filtered_billing_total'])
        ->assertJsonPath('filtered_billing_total', 25000);

    $filtered = $this->getJson(route('service-incidents.index', ['filter[affects_billing]' => 1]));
    $filtered->assertOk()
        ->assertJsonPath('filtered_billing_total', 25000)
        ->assertJsonCount(2, 'data');
});

test('contract show aggregates billing-affecting incidents across its services', function (): void {
    $contract = Contract::factory()->create();
    $serviceA = Service::factory()->create(['contract_id' => $contract->id]);
    $serviceB = Service::factory()->create(['contract_id' => $contract->id]);
    Service::factory()->create(); // unrelated contract — must not be counted

    ServiceIncident::factory()->create([
        'service_id' => $serviceA->id,
        'affects_billing' => true,
        'additional_value' => 40000,
    ]);
    ServiceIncident::factory()->create([
        'service_id' => $serviceB->id,
        'affects_billing' => true,
        'additional_value' => 13750,
    ]);
    ServiceIncident::factory()->create([
        'service_id' => $serviceB->id,
        'affects_billing' => false,
        'additional_value' => 999999,
    ]);

    $response = get(route('contracts.show', $contract));

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('contracts/show')
        ->where('incidentsBillingImpact.count', 2)
        ->where('incidentsBillingImpact.amount', fn ($v) => (float) $v === 53750.0)
    );
});
