<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\DayStatus;
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

test('index redirects to calendar', function (): void {
    $response = get(route('day-statuses.index'));

    $response->assertRedirect(route('day-statuses.calendar', ['year' => now()->year]));
});

test('create behaves as expected', function (): void {
    $response = get(route('day-statuses.create'));

    $response->assertOk();
});

test('store uses form request validation')
    ->assertActionUsesFormRequest(
        \App\Http\Controllers\DayStatusController::class,
        'store',
        \App\Http\Requests\DayStatusStoreRequest::class
    );

test('store saves and redirects', function (): void {
    $date = Carbon::parse(fake()->date());
    $status = fake()->randomElement(['projected', 'executed']);
    $executor = User::factory()->create();
    $executed_at = Carbon::parse(fake()->dateTime());

    $response = post(route('day-statuses.store'), [
        'date' => $date,
        'status' => $status,
        'executor_id' => $executor->id,
        'executed_at' => $executed_at,
    ]);

    $dayStatuses = DayStatus::query()
        ->where('date', $date)
        ->where('status', $status)
        ->where('executor_id', $executor->id)
        ->where('executed_at', $executed_at)
        ->get();
    expect($dayStatuses)->toHaveCount(1);
    $dayStatus = $dayStatuses->first();

    $response->assertRedirect(route('day-statuses.index'));
});

test('show behaves as expected', function (): void {
    $dayStatus = DayStatus::factory()->create();

    $response = get(route('day-statuses.show', $dayStatus));

    $response->assertOk();
});

test('edit behaves as expected', function (): void {
    $dayStatus = DayStatus::factory()->create();

    $response = get(route('day-statuses.edit', $dayStatus));

    $response->assertOk();
});

test('update uses form request validation')
    ->assertActionUsesFormRequest(
        \App\Http\Controllers\DayStatusController::class,
        'update',
        \App\Http\Requests\DayStatusUpdateRequest::class
    );

test('update redirects', function (): void {
    $dayStatus = DayStatus::factory()->create();
    $date = Carbon::parse(fake()->date());
    $status = fake()->randomElement(['projected', 'executed']);
    $executor = User::factory()->create();
    $executed_at = Carbon::parse(fake()->dateTime());

    $response = put(route('day-statuses.update', $dayStatus), [
        'date' => $date,
        'status' => $status,
        'executor_id' => $executor->id,
        'executed_at' => $executed_at,
    ]);

    $dayStatus->refresh();

    $response->assertRedirect(route('day-statuses.index'));

    expect($date)->toEqual($dayStatus->date);
    expect($status)->toEqual($dayStatus->status->value);
    expect($executor->id)->toEqual($dayStatus->executor_id);
    expect($executed_at->timestamp)->toEqual($dayStatus->executed_at);
});

test('destroy deletes and redirects', function (): void {
    $dayStatus = DayStatus::factory()->create();

    $response = delete(route('day-statuses.destroy', $dayStatus));

    $response->assertRedirect(route('day-statuses.index'));

    assertModelMissing($dayStatus);
});
