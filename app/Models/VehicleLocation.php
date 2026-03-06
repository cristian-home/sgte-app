<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Scout\Searchable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class VehicleLocation extends Model
{
    use HasFactory, LogsActivity, Searchable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'vehicle_id',
        'recorded_at',
        'latitude',
        'longitude',
        'is_manual',
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
            'vehicle_id' => 'integer',
            'recorded_at' => 'timestamp',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'is_manual' => 'boolean',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['id', 'vehicle_id', 'recorded_at', 'latitude', 'longitude', 'is_manual']);
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
            'vehicle_id' => $this->vehicle_id,
            'recorded_at' => $this->recorded_at !== null ? date('c', $this->recorded_at) : null,
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
            'is_manual' => $this->is_manual,
        ];
    }
}
