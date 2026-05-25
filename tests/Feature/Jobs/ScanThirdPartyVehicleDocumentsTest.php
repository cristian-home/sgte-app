<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ScanThirdPartyVehicleDocuments;
use App\Models\ThirdParty;
use App\Models\Vehicle;
use App\Notifications\ThirdPartyVehicleDocReminderNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

/**
 * REQ-004 regression for third-party-vehicle-doc-reminders.
 * Verifies the scheduled job groups by ThirdParty, filters on the
 * 30/7/1-day thresholds, dispatches one notification per provider,
 * and routes to the provider's email (with ops CC via config).
 */
beforeEach(function (): void {
    Notification::fake();
});

function providerWithEmail(array $overrides = []): ThirdParty
{
    return ThirdParty::factory()->create(array_merge([
        'is_provider' => true,
        'email' => 'provider@example.test',
    ], $overrides));
}

function thirdPartyVehicle(ThirdParty $provider, array $overrides = []): Vehicle
{
    return Vehicle::factory()->create(array_merge([
        'is_third_party' => true,
        'third_party_id' => $provider->id,
    ], $overrides));
}

test('job sends one digest per provider whose vehicle SOAT expires in 30 days', function (): void {
    $provider = providerWithEmail();
    thirdPartyVehicle($provider, [
        'plate' => 'AAA-001',
        'soat_due_date' => Carbon::today()->addDays(30)->toDateString(),
        'rtm_due_date' => Carbon::today()->addYears(2)->toDateString(),
        'operation_card_due_date' => Carbon::today()->addYears(2)->toDateString(),
    ]);

    (new ScanThirdPartyVehicleDocuments)->handle();

    Notification::assertSentOnDemand(
        ThirdPartyVehicleDocReminderNotification::class,
        function ($notification, $channels, $notifiable) use ($provider) {
            return $notification->provider->id === $provider->id
                && count($notification->entries) === 1
                && $notification->entries[0]['plate'] === 'AAA-001'
                && $notification->entries[0]['document_label'] === 'SOAT'
                && $notification->entries[0]['days_until_expiry'] === 30
                && $notifiable->routes['mail'] === $provider->email;
        },
    );
});

test('job triggers at the 7-day threshold', function (): void {
    $provider = providerWithEmail();
    thirdPartyVehicle($provider, [
        'plate' => 'BBB-002',
        'soat_due_date' => Carbon::today()->addYears(2)->toDateString(),
        'rtm_due_date' => Carbon::today()->addDays(7)->toDateString(),
        'operation_card_due_date' => Carbon::today()->addYears(2)->toDateString(),
    ]);

    (new ScanThirdPartyVehicleDocuments)->handle();

    Notification::assertSentOnDemand(
        ThirdPartyVehicleDocReminderNotification::class,
        fn ($notification) => $notification->entries[0]['document_label'] === 'RTM'
            && $notification->entries[0]['days_until_expiry'] === 7,
    );
});

test('job triggers at the 1-day threshold for operation card', function (): void {
    $provider = providerWithEmail();
    thirdPartyVehicle($provider, [
        'plate' => 'CCC-003',
        'soat_due_date' => Carbon::today()->addYears(2)->toDateString(),
        'rtm_due_date' => Carbon::today()->addYears(2)->toDateString(),
        'operation_card_due_date' => Carbon::today()->addDay()->toDateString(),
    ]);

    (new ScanThirdPartyVehicleDocuments)->handle();

    Notification::assertSentOnDemand(
        ThirdPartyVehicleDocReminderNotification::class,
        fn ($notification) => $notification->entries[0]['document_label'] === 'Tarjeta de Operación'
            && $notification->entries[0]['days_until_expiry'] === 1,
    );
});

test('job groups multiple entries per provider into a single digest', function (): void {
    $provider = providerWithEmail();
    thirdPartyVehicle($provider, [
        'plate' => 'DDD-004',
        'soat_due_date' => Carbon::today()->addDays(30)->toDateString(),
        'rtm_due_date' => Carbon::today()->addDays(7)->toDateString(),
        'operation_card_due_date' => Carbon::today()->addYears(2)->toDateString(),
    ]);
    thirdPartyVehicle($provider, [
        'plate' => 'DDD-005',
        'soat_due_date' => Carbon::today()->addYears(2)->toDateString(),
        'rtm_due_date' => Carbon::today()->addYears(2)->toDateString(),
        'operation_card_due_date' => Carbon::today()->addDay()->toDateString(),
    ]);

    (new ScanThirdPartyVehicleDocuments)->handle();

    Notification::assertSentOnDemandTimes(ThirdPartyVehicleDocReminderNotification::class, 1);

    Notification::assertSentOnDemand(
        ThirdPartyVehicleDocReminderNotification::class,
        function ($notification) use ($provider) {
            if ($notification->provider->id !== $provider->id) {
                return false;
            }
            if (count($notification->entries) !== 3) {
                return false;
            }

            // Sorted most-urgent first.
            return $notification->entries[0]['days_until_expiry'] === 1;
        },
    );
});

test('job sends a separate mail per provider when several providers are affected', function (): void {
    $providerA = providerWithEmail(['email' => 'a@example.test']);
    $providerB = providerWithEmail(['email' => 'b@example.test']);

    thirdPartyVehicle($providerA, [
        'plate' => 'EEE-006',
        'soat_due_date' => Carbon::today()->addDays(30)->toDateString(),
        'rtm_due_date' => Carbon::today()->addYears(2)->toDateString(),
        'operation_card_due_date' => Carbon::today()->addYears(2)->toDateString(),
    ]);
    thirdPartyVehicle($providerB, [
        'plate' => 'FFF-007',
        'soat_due_date' => Carbon::today()->addYears(2)->toDateString(),
        'rtm_due_date' => Carbon::today()->addDays(7)->toDateString(),
        'operation_card_due_date' => Carbon::today()->addYears(2)->toDateString(),
    ]);

    (new ScanThirdPartyVehicleDocuments)->handle();

    Notification::assertSentOnDemandTimes(ThirdPartyVehicleDocReminderNotification::class, 2);
});

test('job skips vehicles not owned by a third party', function (): void {
    Vehicle::factory()->create([
        'is_third_party' => false,
        'third_party_id' => null,
        'soat_due_date' => Carbon::today()->addDays(30)->toDateString(),
    ]);

    (new ScanThirdPartyVehicleDocuments)->handle();

    Notification::assertNothingSent();
});

test('job skips dates that do not match any threshold', function (): void {
    $provider = providerWithEmail();
    thirdPartyVehicle($provider, [
        'plate' => 'GGG-008',
        'soat_due_date' => Carbon::today()->addDays(15)->toDateString(),
        'rtm_due_date' => Carbon::today()->addYears(2)->toDateString(),
        'operation_card_due_date' => Carbon::today()->addYears(2)->toDateString(),
    ]);

    (new ScanThirdPartyVehicleDocuments)->handle();

    Notification::assertNothingSent();
});

test('job skips providers without a contact email', function (): void {
    // third_parties.email is declared NOT NULL in the primary migration,
    // so "no email" is represented with an empty string. The job's
    // `is_string && !== ''` guard handles both null and empty.
    $provider = ThirdParty::factory()->create([
        'is_provider' => true,
        'email' => '',
    ]);
    thirdPartyVehicle($provider, [
        'soat_due_date' => Carbon::today()->addDays(30)->toDateString(),
    ]);

    (new ScanThirdPartyVehicleDocuments)->handle();

    Notification::assertNothingSent();
});

test('notification applies the ops CC when the config is set', function (): void {
    config()->set('sgte.ops_alert_email', 'ops@sgte.app');

    $provider = providerWithEmail();
    $notification = new ThirdPartyVehicleDocReminderNotification($provider, [
        [
            'plate' => 'HHH-009',
            'document_label' => 'SOAT',
            'due_date' => Carbon::today()->addDays(30)->toDateString(),
            'days_until_expiry' => 30,
        ],
    ]);

    $message = $notification->toMail($provider);
    expect($message->cc)->toBeArray();
    expect($message->cc[0][0] ?? null)->toBe('ops@sgte.app');
});

test('notification omits CC when the ops alert email is not configured', function (): void {
    config()->set('sgte.ops_alert_email', null);

    $provider = providerWithEmail();
    $notification = new ThirdPartyVehicleDocReminderNotification($provider, [
        [
            'plate' => 'III-010',
            'document_label' => 'RTM',
            'due_date' => Carbon::today()->addDays(7)->toDateString(),
            'days_until_expiry' => 7,
        ],
    ]);

    $message = $notification->toMail($provider);
    expect($message->cc)->toBe([]);
});
