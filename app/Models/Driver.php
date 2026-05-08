<?php

namespace App\Models;

use App\Concerns\HasTimezone;
use App\Enums\LicenseCategory;
use App\Support\Tz;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Driver extends Model
{
    use HasFactory, HasTimezone, LogsActivity, Searchable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'document_type_id',
        'identification_number',
        'first_name',
        'second_name',
        'first_lastname',
        'second_lastname',
        'municipality_id',
        'address',
        'phone',
        'email',
        'license_category',
        'timezone',
        'license_due_at',
        // Wall-clock virtual: setter projects to license_due_at using
        // the row's TZ. Listed AFTER `timezone` so mass-assignment runs
        // it once the TZ is known.
        'license_due_date',
        'eps_id',
        'pension_fund_id',
        'severance_fund_id',
        'has_social_security',
        'active',
    ];

    /**
     * @var list<string>
     */
    protected $appends = ['license_due_date'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'user_id' => 'integer',
            'document_type_id' => 'integer',
            'municipality_id' => 'integer',
            'eps_id' => 'integer',
            'pension_fund_id' => 'integer',
            'severance_fund_id' => 'integer',
            'license_category' => LicenseCategory::class,
            'license_due_at' => 'immutable_datetime:Y-m-d H:i:sP',
            'timezone' => 'string',
            'has_social_security' => 'boolean',
            'active' => 'boolean',
        ];
    }

    /**
     * Wall-clock last day on which the license is valid, projected to
     * `Y-m-d` in the driver's timezone. The underlying `license_due_at`
     * is the half-open exclusive instant (next-midnight after the
     * conventional last day).
     */
    public function getLicenseDueDateAttribute(): ?string
    {
        if ($this->license_due_at === null) {
            return null;
        }

        return $this->license_due_at->setTimezone($this->resolveTimezone())->subSecond()->format('Y-m-d');
    }

    /**
     * Wall-clock setter — projects `Y-m-d` (or DateTime) into a
     * `license_due_at` instant using the row's TZ.
     */
    public function setLicenseDueDateAttribute(mixed $value): void
    {
        if ($value === null || $value === '') {
            return;
        }
        $date = $value instanceof \DateTimeInterface
            ? $value->format('Y-m-d')
            : (preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $value, $m) ? $m[0] : null);
        if ($date === null) {
            return;
        }
        $this->setAttribute('license_due_at', Tz::endOfDayInTzAsUtc($date, $this->resolveTimezone())->utc());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function eps(): BelongsTo
    {
        return $this->belongsTo(Eps::class);
    }

    public function pensionFund(): BelongsTo
    {
        return $this->belongsTo(PensionFund::class);
    }

    public function severanceFund(): BelongsTo
    {
        return $this->belongsTo(SeveranceFund::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['id', 'document_type_id', 'identification_number', 'first_name', 'second_name', 'first_lastname', 'second_lastname', 'municipality_id', 'address', 'phone', 'email', 'license_category', 'license_due_at', 'timezone', 'eps_id', 'pension_fund_id', 'severance_fund_id', 'has_social_security', 'active']);
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
            'document_type_id' => $this->document_type_id,
            'identification_number' => $this->identification_number,
            'first_name' => $this->first_name,
            'second_name' => $this->second_name,
            'first_lastname' => $this->first_lastname,
            'second_lastname' => $this->second_lastname,
            'municipality_id' => $this->municipality_id,
            'address' => $this->address,
            'phone' => $this->phone,
            'email' => $this->email,
            'license_category' => $this->license_category,
            'license_due_date' => $this->license_due_date,
            'license_due_at' => $this->license_due_at?->toIso8601String(),
            'timezone' => $this->resolveTimezone(),
            'eps_id' => $this->eps_id,
            'pension_fund_id' => $this->pension_fund_id,
            'severance_fund_id' => $this->severance_fund_id,
            'has_social_security' => $this->has_social_security,
            'active' => $this->active,
        ];
    }
}
