<?php

namespace App\Jobs;

use App\Models\ThirdParty;
use App\Models\Vehicle;
use App\Notifications\ThirdPartyVehicleDocReminderNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as FoundationQueueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

/**
 * REQ-004 third-party fleet document sweep
 * (third-party-vehicle-doc-reminders).
 *
 * Runs nightly via the scheduler. Finds third-party vehicles whose
 * SOAT / RTM / Tarjeta de Operación expires in exactly 30, 7, or 1 day
 * from today, groups the matches by owning ThirdParty, and dispatches
 * a single digest mail per provider (with ops on CC). Always-null email
 * on the provider → skipped; no exception.
 */
class ScanThirdPartyVehicleDocuments implements ShouldQueue
{
    use FoundationQueueable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var list<int> */
    public const THRESHOLDS = [30, 7, 1];

    /** @var array<string, string> */
    public const DOCUMENT_LABELS = [
        'soat_due_date' => 'SOAT',
        'rtm_due_date' => 'RTM',
        'operation_card_due_date' => 'Tarjeta de Operación',
    ];

    public function handle(): void
    {
        // Provider-indexed digest: third_party_id => list<entry>.
        $entriesByProvider = [];

        foreach (self::THRESHOLDS as $days) {
            $targetDate = Carbon::now((string) config('app.operation_tz'))->startOfDay()->addDays($days);

            foreach (self::DOCUMENT_LABELS as $column => $label) {
                $vehicles = Vehicle::query()
                    ->where('is_third_party', true)
                    ->whereNotNull('third_party_id')
                    ->whereDate($column, $targetDate)
                    ->get(['id', 'plate', 'third_party_id', $column]);

                foreach ($vehicles as $vehicle) {
                    $dueValue = $vehicle->{$column};
                    $dueString = $dueValue instanceof Carbon
                        ? $dueValue->toDateString()
                        : (string) $dueValue;

                    $entriesByProvider[$vehicle->third_party_id][] = [
                        'plate' => $vehicle->plate,
                        'document_label' => $label,
                        'due_date' => $dueString,
                        'days_until_expiry' => $days,
                    ];
                }
            }
        }

        if ($entriesByProvider === []) {
            return;
        }

        $providers = ThirdParty::query()
            ->whereIn('id', array_keys($entriesByProvider))
            ->get();

        foreach ($providers as $provider) {
            if (! is_string($provider->email) || $provider->email === '') {
                continue;
            }

            $entries = $entriesByProvider[$provider->id] ?? [];
            if ($entries === []) {
                continue;
            }

            // Sort most-urgent first so the urgent-row tint at the top of
            // the digest aligns with the scannable "days restantes" column.
            usort($entries, fn ($a, $b) => $a['days_until_expiry'] <=> $b['days_until_expiry']);

            Notification::route('mail', $provider->email)
                ->notify(new ThirdPartyVehicleDocReminderNotification($provider, $entries));
        }
    }
}
