<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\ServiceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Service extends Model
{
    use Concerns\SearchesDatabase;
    use HasFactory, SoftDeletes;
    use LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'contract_id',
        'vehicle_id',
        'driver_id',
        'invoice_id',
        'service_date_local',
        'origin_municipality_id',
        'origin_address',
        'origin_coordinates',
        'origin_coordinates_source',
        'origin_coordinates_accuracy',
        'destination_municipality_id',
        'destination_address',
        'destination_coordinates',
        'destination_coordinates_source',
        'destination_coordinates_accuracy',
        'planned_start_at',
        'planned_duration',
        'actual_start_at',
        'actual_end_at',
        'timezone',
        'unit_value',
        'quantity',
        'billing_group',
        'payment_method',
        'service_status',
        'manual_entry_justification',
        'driver_declined_at',
        'driver_decline_reason',
        'route_geometry',
        'route_distance_m',
        'route_duration_s',
        'route_fetched_at',
        'route_source',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'service_date',
        'planned_start_local',
        'actual_start_local',
        'actual_end_local',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'contract_id' => 'integer',
            'vehicle_id' => 'integer',
            'driver_id' => 'integer',
            'origin_municipality_id' => 'integer',
            'destination_municipality_id' => 'integer',
            'invoice_id' => 'integer',
            // Persist instants with the explicit offset so the SQLite
            // test driver preserves TZ on round-trip. PostgreSQL's
            // TIMESTAMPTZ handles offsets natively; SQLite stores
            // strings, and a TZ-naive 'Y-m-d H:i:s' gets re-parsed in
            // PHP's default TZ on read — which the cross-TZ tests
            // deliberately move around. The 'P' suffix keeps the
            // instant stable in either driver.
            'service_date_local' => 'immutable_date',
            'planned_start_at' => 'immutable_datetime:Y-m-d H:i:sP',
            'actual_start_at' => 'immutable_datetime:Y-m-d H:i:sP',
            'actual_end_at' => 'immutable_datetime:Y-m-d H:i:sP',
            'timezone' => 'string',
            'unit_value' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
            'service_status' => ServiceStatus::class,
            'driver_declined_at' => 'immutable_datetime:Y-m-d H:i:sP',
            'route_geometry' => 'array',
            'route_distance_m' => 'integer',
            'route_duration_s' => 'integer',
            'route_fetched_at' => 'immutable_datetime:Y-m-d H:i:sP',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Service $service): void {
            // Cached route invalidation: if either coord pair changed,
            // wipe the cache so the saved hook can re-queue a fetch.
            if ($service->isDirty('origin_coordinates') || $service->isDirty('destination_coordinates')) {
                $service->route_geometry = null;
                $service->route_distance_m = null;
                $service->route_duration_s = null;
                $service->route_fetched_at = null;
                $service->route_source = null;
            }

            // Keep the denormalized day-bucket column in sync with the
            // wall-clock projection of `planned_start_at` in the service's
            // own timezone. Day-range queries (Gantt, Day Summary, Annual
            // Calendar) rely on a BTree index over `service_date_local`.
            if (! $service->planned_start_at instanceof \DateTimeInterface) {
                return;
            }

            $tz = Str::of((string) $service->timezone)->trim()->toString();
            if ($tz === '') {
                $tz = config('app.operation_tz');
            }

            $service->service_date_local = Carbon::instance($service->planned_start_at)
                ->setTimezone($tz)
                ->toDateString();
        });

        // Cache refresh hooks split across created/updated because:
        //   - wasRecentlyCreated stays true on the same PHP instance even
        //     after a subsequent update, so saved() can't distinguish
        //     insert from update via that flag alone.
        //   - wasChanged() returns false on a fresh insert (no prior state
        //     to compare against), so a coord-aware updated() needs its
        //     own callback that's only fired during an UPDATE save.
        static::created(function (Service $service): void {
            if (empty($service->origin_coordinates) || empty($service->destination_coordinates)) {
                return;
            }
            \App\Jobs\FetchServiceRoute::dispatch($service);
        });

        static::updated(function (Service $service): void {
            if (! $service->wasChanged('origin_coordinates') && ! $service->wasChanged('destination_coordinates')) {
                return;
            }
            if (empty($service->origin_coordinates) || empty($service->destination_coordinates)) {
                return;
            }
            \App\Jobs\FetchServiceRoute::dispatch($service);
        });
    }

    /**
     * Wall-clock service date (Y-m-d) projected in the service's timezone.
     */
    public function getServiceDateAttribute(): ?string
    {
        if ($this->service_date_local instanceof \DateTimeInterface) {
            return $this->service_date_local->format('Y-m-d');
        }

        return $this->planned_start_at?->setTimezone($this->resolveTimezone())->format('Y-m-d');
    }

    /**
     * Wall-clock planned start time (HH:mm) projected in the service's timezone.
     */
    public function getPlannedStartLocalAttribute(): ?string
    {
        return $this->planned_start_at?->setTimezone($this->resolveTimezone())->format('H:i');
    }

    /**
     * Wall-clock actual start time (HH:mm) projected in the service's timezone.
     */
    public function getActualStartLocalAttribute(): ?string
    {
        return $this->actual_start_at?->setTimezone($this->resolveTimezone())->format('H:i');
    }

    /**
     * Wall-clock actual end time (HH:mm) projected in the service's timezone.
     */
    public function getActualEndLocalAttribute(): ?string
    {
        return $this->actual_end_at?->setTimezone($this->resolveTimezone())->format('H:i');
    }

    protected function resolveTimezone(): string
    {
        $tz = Str::of((string) $this->timezone)->trim()->toString();

        return $tz === '' ? (string) config('app.operation_tz') : $tz;
    }

    /**
     * Wall-clock setter: writing `service_date` aliases to
     * `service_date_local` AND shifts `planned_start_at` (and any
     * `actual_*_at` instants) onto the new date, preserving the
     * wall-clock time-of-day in the model's timezone. This mirrors the
     * "user picked a different day" affordance in the form / tests.
     */
    public function setServiceDateAttribute(mixed $value): void
    {
        $date = $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : (string) $value;
        $this->attributes['service_date_local'] = $date;

        $tz = $this->resolveTimezone();
        foreach (['planned_start_at', 'actual_start_at', 'actual_end_at'] as $col) {
            if (! isset($this->attributes[$col]) || $this->attributes[$col] === null) {
                continue;
            }

            try {
                $existing = \Carbon\CarbonImmutable::parse($this->attributes[$col])->setTimezone($tz);
                $shifted = $existing->setDate(
                    (int) substr($date, 0, 4),
                    (int) substr($date, 5, 2),
                    (int) substr($date, 8, 2),
                );
                $this->setAttribute($col, $shifted->utc());
            } catch (\Exception) {
                // Leave the original value alone if parsing fails.
            }
        }

        // Initialize planned_start_at when no instant exists yet.
        if (! isset($this->attributes['planned_start_at']) || $this->attributes['planned_start_at'] === null) {
            $this->setPlannedStartTimeAttribute('00:00', $date);
        }
    }

    /**
     * Wall-clock setter: writing `planned_start_time` (HH:mm[:ss]) is
     * projected onto a UTC instant using the model's timezone +
     * service_date_local.
     */
    public function setPlannedStartTimeAttribute(mixed $value, ?string $dateOverride = null): void
    {
        $instant = $this->wallClockToInstant($value, $dateOverride);
        if ($instant !== null) {
            $this->setAttribute('planned_start_at', $instant->utc());
        }
    }

    public function setActualStartTimeAttribute(mixed $value): void
    {
        $instant = $this->wallClockToInstant($value);
        $this->setAttribute('actual_start_at', $instant?->utc());
    }

    public function setActualEndTimeAttribute(mixed $value): void
    {
        $instant = $this->wallClockToInstant($value);
        $this->setAttribute('actual_end_at', $instant?->utc());
    }

    /**
     * Project a wall-clock HH:mm[:ss] string into a UTC instant using the
     * model's timezone and (date_override or service_date_local). Returns
     * null when value is empty.
     */
    protected function wallClockToInstant(mixed $value, ?string $dateOverride = null): ?\Carbon\CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        $time = $value instanceof \DateTimeInterface
            ? $value->format('H:i')
            : substr((string) $value, 0, 5);

        $date = $dateOverride
            ?? (isset($this->attributes['service_date_local']) ? substr((string) $this->attributes['service_date_local'], 0, 10) : null)
            ?? Carbon::now($this->resolveTimezone())->toDateString();

        try {
            return \Carbon\CarbonImmutable::createFromFormat('Y-m-d H:i', "{$date} {$time}", $this->resolveTimezone());
        } catch (\Exception) {
            return null;
        }
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function originMunicipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class, 'origin_municipality_id');
    }

    public function destinationMunicipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class, 'destination_municipality_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function serviceIncidents(): HasMany
    {
        return $this->hasMany(ServiceIncident::class);
    }

    public function fuec(): HasOne
    {
        return $this->hasOne(Fuec::class);
    }

    public function fuecs(): HasMany
    {
        return $this->hasMany(Fuec::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(VehicleLocation::class);
    }

    /**
     * Alias for eager-loading recent locations via driver dashboard
     * + show pages. Never constrained here — the caller orders + limits.
     */
    public function recentLocations(): HasMany
    {
        return $this->hasMany(VehicleLocation::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['id', 'contract_id', 'vehicle_id', 'driver_id', 'invoice_id', 'service_date_local', 'origin_municipality_id', 'origin_address', 'origin_coordinates', 'origin_coordinates_source', 'origin_coordinates_accuracy', 'destination_municipality_id', 'destination_address', 'destination_coordinates', 'destination_coordinates_source', 'destination_coordinates_accuracy', 'planned_start_at', 'planned_duration', 'actual_start_at', 'actual_end_at', 'timezone', 'unit_value', 'quantity', 'billing_group', 'payment_method', 'service_status', 'manual_entry_justification', 'driver_declined_at', 'driver_decline_reason']);
    }

    /**
     * @return array<int, string>
     */
    public function searchableColumns(): array
    {
        return ['origin_address', 'destination_address', 'billing_group', ['driver.first_name', 'driver.first_lastname']];
    }
}
