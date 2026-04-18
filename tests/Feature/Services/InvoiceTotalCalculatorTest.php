<?php

namespace Tests\Feature\Services;

use App\Models\IncidentType;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\ServiceIncident;
use App\Services\InvoiceTotalCalculator;

beforeEach(function () {
    $this->calculator = new InvoiceTotalCalculator;
});

test('recomputeFor returns zero for an invoice with no services', function (): void {
    $invoice = Invoice::factory()->create();

    $total = $this->calculator->recomputeFor($invoice);

    expect($total)->toBe('0.00');
    expect($invoice->fresh()->total_value)->toBe('0.00');
});

test('recomputeFor sums unit_value times quantity across attached services', function (): void {
    $invoice = Invoice::factory()->create();

    Service::factory()->create([
        'invoice_id' => $invoice->id,
        'unit_value' => 1000,
        'quantity' => 2,
    ]);
    Service::factory()->create([
        'invoice_id' => $invoice->id,
        'unit_value' => 500,
        'quantity' => 3,
    ]);

    $total = $this->calculator->recomputeFor($invoice);

    expect($total)->toBe('3500.00');
});

test('recomputeFor adds billing-affecting incident additional_value to the sum', function (): void {
    $invoice = Invoice::factory()->create();
    $service = Service::factory()->create([
        'invoice_id' => $invoice->id,
        'unit_value' => 1000,
        'quantity' => 1,
    ]);

    ServiceIncident::factory()->create([
        'service_id' => $service->id,
        'incident_type_id' => IncidentType::factory()->create()->id,
        'affects_billing' => true,
        'additional_value' => 250,
    ]);

    $total = $this->calculator->recomputeFor($invoice);

    expect($total)->toBe('1250.00');
});

test('recomputeFor ignores non-billing-affecting incidents', function (): void {
    $invoice = Invoice::factory()->create();
    $service = Service::factory()->create([
        'invoice_id' => $invoice->id,
        'unit_value' => 1000,
        'quantity' => 1,
    ]);

    ServiceIncident::factory()->create([
        'service_id' => $service->id,
        'incident_type_id' => IncidentType::factory()->create()->id,
        'affects_billing' => false,
        'additional_value' => 999999,
    ]);

    $total = $this->calculator->recomputeFor($invoice);

    expect($total)->toBe('1000.00');
});

test('recomputeFor handles services with zero values correctly', function (): void {
    $invoice = Invoice::factory()->create();

    Service::factory()->create([
        'invoice_id' => $invoice->id,
        'unit_value' => 0,
        'quantity' => 5,
    ]);
    Service::factory()->create([
        'invoice_id' => $invoice->id,
        'unit_value' => 2000,
        'quantity' => 3,
    ]);

    $total = $this->calculator->recomputeFor($invoice);

    expect($total)->toBe('6000.00');
});

test('recomputeFor treats null additional_value on incidents as zero contribution', function (): void {
    $invoice = Invoice::factory()->create();
    $service = Service::factory()->create([
        'invoice_id' => $invoice->id,
        'unit_value' => 1000,
        'quantity' => 1,
    ]);

    ServiceIncident::factory()->create([
        'service_id' => $service->id,
        'incident_type_id' => IncidentType::factory()->create()->id,
        'affects_billing' => true,
        'additional_value' => null,
    ]);

    $total = $this->calculator->recomputeFor($invoice);

    expect($total)->toBe('1000.00');
});

test('recomputeFor persists the new total to the database', function (): void {
    $invoice = Invoice::factory()->create(['total_value' => 0]);
    Service::factory()->create([
        'invoice_id' => $invoice->id,
        'unit_value' => 750,
        'quantity' => 4,
    ]);

    $this->calculator->recomputeFor($invoice);

    expect($invoice->fresh()->total_value)->toBe('3000.00');
});

test('computeFor returns the value without persisting', function (): void {
    $invoice = Invoice::factory()->create(['total_value' => 9999]);
    Service::factory()->create([
        'invoice_id' => $invoice->id,
        'unit_value' => 100,
        'quantity' => 5,
    ]);

    $computed = $this->calculator->computeFor($invoice);

    expect($computed)->toBe('500.00');
    expect($invoice->fresh()->total_value)->toBe('9999.00');
});

test('computeFor correctly sums services with billing-affecting incidents', function (): void {
    $invoice = Invoice::factory()->create(['total_value' => 0]);
    $serviceA = Service::factory()->create([
        'invoice_id' => $invoice->id,
        'unit_value' => 1000,
        'quantity' => 2,
    ]);
    $serviceB = Service::factory()->create([
        'invoice_id' => $invoice->id,
        'unit_value' => 500,
        'quantity' => 1,
    ]);

    $type = IncidentType::factory()->create();

    ServiceIncident::factory()->create([
        'service_id' => $serviceA->id,
        'incident_type_id' => $type->id,
        'affects_billing' => true,
        'additional_value' => 300,
    ]);
    ServiceIncident::factory()->create([
        'service_id' => $serviceB->id,
        'incident_type_id' => $type->id,
        'affects_billing' => true,
        'additional_value' => 200,
    ]);

    $computed = $this->calculator->computeFor($invoice);

    // 2000 + 500 + 300 + 200 = 3000
    expect($computed)->toBe('3000.00');
});
