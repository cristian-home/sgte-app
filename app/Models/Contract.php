<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Contract extends Model
{
    use HasFactory, SoftDeletes;
    use LogsActivity, Searchable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'contract_number',
        'third_party_id',
        'contract_object',
        'start_date',
        'end_date',
        'route_description',
        'is_generic',
        'active',
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
            'third_party_id' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'is_generic' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function thirdParty(): BelongsTo
    {
        return $this->belongsTo(ThirdParty::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['id', 'contract_number', 'third_party_id', 'contract_object', 'start_date', 'end_date', 'route_description', 'is_generic', 'active']);
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
            'contract_number' => $this->contract_number,
            'third_party_id' => $this->third_party_id,
            'contract_object' => $this->contract_object,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'route_description' => $this->route_description,
            'is_generic' => $this->is_generic,
            'active' => $this->active,
        ];
    }
}
