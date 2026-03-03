<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Fuec;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role as SpatieRole;

use function Pest\Laravel\assertModelMissing;
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
    $fuecs = Fuec::factory()->count(3)->create();

    $response = get(route('fuecs.index'));

    $response->assertOk();
});

test('create behaves as expected', function (): void {
    $response = get(route('fuecs.create'));

    $response->assertOk();
});

test('store uses form request validation')
    ->assertActionUsesFormRequest(
        \App\Http\Controllers\FuecController::class,
        'store',
        \App\Http\Requests\FuecStoreRequest::class
    );

test('store saves and redirects', function (): void {
    $service = Service::factory()->create();
    $consecutive_number = fake()->numberBetween(1, 99999);
    $generated_at = Carbon::parse(fake()->dateTime());
    $qr_code = fake()->word();
    $status = fake()->randomElement(['active', 'cancelled']);
    $pdf_url = fake()->word();

    $response = post(route('fuecs.store'), [
        'service_id' => $service->id,
        'consecutive_number' => $consecutive_number,
        'generated_at' => $generated_at,
        'qr_code' => $qr_code,
        'status' => $status,
        'pdf_url' => $pdf_url,
    ]);

    $fuecs = Fuec::query()
        ->where('service_id', $service->id)
        ->where('consecutive_number', $consecutive_number)
        ->where('generated_at', $generated_at)
        ->where('qr_code', $qr_code)
        ->where('status', $status)
        ->where('pdf_url', $pdf_url)
        ->get();
    expect($fuecs)->toHaveCount(1);
    $fuec = $fuecs->first();

    $response->assertRedirect(route('fuecs.index'));
});

test('show behaves as expected', function (): void {
    $fuec = Fuec::factory()->create();

    $response = get(route('fuecs.show', $fuec));

    $response->assertOk();
});

test('edit behaves as expected', function (): void {
    $fuec = Fuec::factory()->create();

    $response = get(route('fuecs.edit', $fuec));

    $response->assertOk();
});

test('update uses form request validation')
    ->assertActionUsesFormRequest(
        \App\Http\Controllers\FuecController::class,
        'update',
        \App\Http\Requests\FuecUpdateRequest::class
    );

test('update redirects', function (): void {
    $fuec = Fuec::factory()->create();
    $service = Service::factory()->create();
    $consecutive_number = fake()->numberBetween(1, 99999);
    $generated_at = Carbon::parse(fake()->dateTime());
    $qr_code = fake()->word();
    $status = fake()->randomElement(['active', 'cancelled']);
    $pdf_url = fake()->word();

    $response = put(route('fuecs.update', $fuec), [
        'service_id' => $service->id,
        'consecutive_number' => $consecutive_number,
        'generated_at' => $generated_at,
        'qr_code' => $qr_code,
        'status' => $status,
        'pdf_url' => $pdf_url,
    ]);

    $fuec->refresh();

    $response->assertRedirect(route('fuecs.index'));

    expect($service->id)->toEqual($fuec->service_id);
    expect($consecutive_number)->toEqual($fuec->consecutive_number);
    expect($generated_at->timestamp)->toEqual($fuec->generated_at);
    expect($qr_code)->toEqual($fuec->qr_code);
    expect($status)->toEqual($fuec->status->value);
    expect($pdf_url)->toEqual($fuec->pdf_url);
});

test('destroy deletes and redirects', function (): void {
    $fuec = Fuec::factory()->create();

    $response = delete(route('fuecs.destroy', $fuec));

    $response->assertRedirect(route('fuecs.index'));

    assertModelMissing($fuec);
});
