<?php

namespace App\Models;

use App\Enums\FuecStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Scout\Searchable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Fuec extends Model
{
    use HasFactory, LogsActivity, Searchable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'service_id',
        'consecutive_number',
        'generated_at',
        'qr_code',
        'status',
        'pdf_url',
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
            'service_id' => 'integer',
            'generated_at' => 'timestamp',
            'status' => FuecStatus::class,
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['id', 'service_id', 'consecutive_number', 'generated_at', 'qr_code', 'status', 'pdf_url']);
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
            'service_id' => $this->service_id,
            'consecutive_number' => $this->consecutive_number,
            'generated_at' => $this->generated_at,
            'qr_code' => $this->qr_code,
            'status' => $this->status,
            'pdf_url' => $this->pdf_url,
        ];
    }
}
