<?php

namespace App\Models;

use App\Enums\VehicleStatus;
use App\Enums\VehicleType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Vehicle extends Model
{
    use HasFactory, SoftDeletes;
    use LogsActivity, Searchable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'internal_code',
        'plate',
        'mobile_number',
        'brand',
        'line',
        'model_year',
        'type',
        'engine_number',
        'chassis_number',
        'capacity',
        'municipality_id',
        'is_third_party',
        'third_party_id',
        'soat_due_date',
        'rtm_due_date',
        'operation_card_due_date',
        'status',
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
            'type' => VehicleType::class,
            'municipality_id' => 'integer',
            'is_third_party' => 'boolean',
            'third_party_id' => 'integer',
            'soat_due_date' => 'date',
            'rtm_due_date' => 'date',
            'operation_card_due_date' => 'date',
            'status' => VehicleStatus::class,
        ];
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function thirdParty(): BelongsTo
    {
        return $this->belongsTo(ThirdParty::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function vehicleLocations(): HasMany
    {
        return $this->hasMany(VehicleLocation::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['id', 'internal_code', 'plate', 'mobile_number', 'brand', 'line', 'model_year', 'type', 'engine_number', 'chassis_number', 'capacity', 'municipality_id', 'is_third_party', 'third_party_id', 'soat_due_date', 'rtm_due_date', 'operation_card_due_date', 'status']);
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
            'internal_code' => $this->internal_code,
            'plate' => $this->plate,
            'mobile_number' => $this->mobile_number,
            'brand' => $this->brand,
            'line' => $this->line,
            'model_year' => $this->model_year,
            'type' => $this->type,
            'engine_number' => $this->engine_number,
            'chassis_number' => $this->chassis_number,
            'capacity' => $this->capacity,
            'municipality_id' => $this->municipality_id,
            'is_third_party' => $this->is_third_party,
            'third_party_id' => $this->third_party_id,
            'soat_due_date' => $this->soat_due_date,
            'rtm_due_date' => $this->rtm_due_date,
            'operation_card_due_date' => $this->operation_card_due_date,
            'status' => $this->status,
        ];
    }
}
