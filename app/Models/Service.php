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
        'destination_municipality_id',
        'destination_address',
        'destination_coordinates',
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
            'service_date_local' => 'immutable_date',
            'planned_start_at' => 'immutable_datetime',
            'actual_start_at' => 'immutable_datetime',
            'actual_end_at' => 'immutable_datetime',
            'timezone' => 'string',
            'unit_value' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
            'service_status' => ServiceStatus::class,
            'driver_declined_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Service $service): void {
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
            ->logOnly(['id', 'contract_id', 'vehicle_id', 'driver_id', 'invoice_id', 'service_date_local', 'origin_municipality_id', 'origin_address', 'origin_coordinates', 'destination_municipality_id', 'destination_address', 'destination_coordinates', 'planned_start_at', 'planned_duration', 'actual_start_at', 'actual_end_at', 'timezone', 'unit_value', 'quantity', 'billing_group', 'payment_method', 'service_status', 'manual_entry_justification', 'driver_declined_at', 'driver_decline_reason']);
    }

    /**
     * @return array<int, string>
     */
    public function searchableColumns(): array
    {
        return ['origin_address', 'destination_address', 'billing_group', ['driver.first_name', 'driver.first_lastname']];
    }
}
