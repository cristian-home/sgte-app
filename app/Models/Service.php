<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Service extends Model
{
    use HasFactory, SoftDeletes;
    use LogsActivity, Searchable;

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
        'service_date',
        'origin',
        'destination',
        'planned_start_time',
        'planned_duration',
        'actual_start_time',
        'actual_end_time',
        'unit_value',
        'quantity',
        'billing_group',
        'payment_method',
        'service_status',
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
            'invoice_id' => 'integer',
            'service_date' => 'date',
            'unit_value' => 'decimal:2',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['id', 'contract_id', 'vehicle_id', 'driver_id', 'invoice_id', 'service_date', 'origin', 'destination', 'planned_start_time', 'planned_duration', 'actual_start_time', 'actual_end_time', 'unit_value', 'quantity', 'billing_group', 'payment_method', 'service_status']);
    }

    /**
     * Get the value used to index the model.
     */
    public function getScoutKey(): mixed
    {
        return $this->id;
    }

    /**
     * Get the indexable data array for the model.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => (string) $this->id,
            'contract_id' => $this->contract_id,
            'vehicle_id' => $this->vehicle_id,
            'driver_id' => $this->driver_id,
            'invoice_id' => $this->invoice_id,
            'service_date' => $this->service_date,
            'origin' => $this->origin,
            'destination' => $this->destination,
            'planned_start_time' => $this->planned_start_time,
            'planned_duration' => $this->planned_duration,
            'actual_start_time' => $this->actual_start_time,
            'actual_end_time' => $this->actual_end_time,
            'unit_value' => $this->unit_value,
            'quantity' => $this->quantity,
            'billing_group' => $this->billing_group,
            'payment_method' => $this->payment_method,
            'service_status' => $this->service_status,
        ];
    }
}
