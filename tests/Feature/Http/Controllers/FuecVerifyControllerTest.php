<?php

use App\Enums\FuecStatus;
use App\Models\Fuec;
use App\Models\FuecNumberRange;
use App\Models\Service;

use function Pest\Laravel\get;

beforeEach(function (): void {
    config()->set('sgte.fuec_enabled', true);
});

test('public verify renders VIGENTE for an active FUEC', function (): void {
    $fuec = Fuec::factory()->create([
        'service_id' => Service::factory()->create()->id,
        'fuec_number_range_id' => FuecNumberRange::factory()->create()->id,
        'status' => FuecStatus::Active,
    ]);

    $response = get(route('fuec.verify', ['uuid' => $fuec->uuid]));
    $response->assertOk()
        ->assertSee('VIGENTE', false)
        ->assertDontSee('ANULADO', false);
});

test('public verify renders ANULADO for a cancelled FUEC', function (): void {
    $fuec = Fuec::factory()->cancelled()->create([
        'service_id' => Service::factory()->create()->id,
        'fuec_number_range_id' => FuecNumberRange::factory()->create()->id,
    ]);

    $response = get(route('fuec.verify', ['uuid' => $fuec->uuid]));
    $response->assertOk()
        ->assertSee('ANULADO', false)
        ->assertSee('Documento Anulado', false);
});

test('public verify 404s on an unknown uuid', function (): void {
    get(route('fuec.verify', ['uuid' => 'deadbeef-dead-beef-dead-beefdeadbeef']))
        ->assertNotFound();
});

test('public verify 404s when the module is disabled', function (): void {
    $fuec = Fuec::factory()->create([
        'service_id' => Service::factory()->create()->id,
        'fuec_number_range_id' => FuecNumberRange::factory()->create()->id,
    ]);

    config()->set('sgte.fuec_enabled', false);

    get(route('fuec.verify', ['uuid' => $fuec->uuid]))
        ->assertNotFound();
});

test('public verify works without authentication', function (): void {
    $fuec = Fuec::factory()->create([
        'service_id' => Service::factory()->create()->id,
        'fuec_number_range_id' => FuecNumberRange::factory()->create()->id,
        'status' => FuecStatus::Active,
    ]);

    // No actingAs — simulates a government inspector scanning the QR cold.
    get(route('fuec.verify', ['uuid' => $fuec->uuid]))
        ->assertOk();
});
