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
        'service_date',
        'origin_municipality_id',
        'origin_address',
        'origin_coordinates',
        'destination_municipality_id',
        'destination_address',
        'destination_coordinates',
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
            'origin_municipality_id' => 'integer',
            'destination_municipality_id' => 'integer',
            'invoice_id' => 'integer',
            'service_date' => 'date',
            'unit_value' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
            'service_status' => ServiceStatus::class,
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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['id', 'contract_id', 'vehicle_id', 'driver_id', 'invoice_id', 'service_date', 'origin_municipality_id', 'origin_address', 'origin_coordinates', 'destination_municipality_id', 'destination_address', 'destination_coordinates', 'planned_start_time', 'planned_duration', 'actual_start_time', 'actual_end_time', 'unit_value', 'quantity', 'billing_group', 'payment_method', 'service_status']);
    }

    /**
     * @return array<int, string>
     */
    public function searchableColumns(): array
    {
        return ['origin_address', 'destination_address', 'billing_group', ['driver.first_name', 'driver.first_lastname']];
    }
}
