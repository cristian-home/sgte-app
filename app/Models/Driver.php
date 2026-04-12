<?php

namespace App\Models;

use App\Enums\LicenseCategory;
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
    use HasFactory, SoftDeletes;
    use LogsActivity, Searchable;

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
        'license_due_date',
        'eps_id',
        'pension_fund_id',
        'severance_fund_id',
        'has_social_security',
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
            'user_id' => 'integer',
            'document_type_id' => 'integer',
            'municipality_id' => 'integer',
            'eps_id' => 'integer',
            'pension_fund_id' => 'integer',
            'severance_fund_id' => 'integer',
            'license_category' => LicenseCategory::class,
            'license_due_date' => 'date',
            'has_social_security' => 'boolean',
            'active' => 'boolean',
        ];
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
            ->logOnly(['id', 'document_type_id', 'identification_number', 'first_name', 'second_name', 'first_lastname', 'second_lastname', 'municipality_id', 'address', 'phone', 'email', 'license_category', 'license_due_date', 'eps_id', 'pension_fund_id', 'severance_fund_id', 'has_social_security', 'active']);
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
            'license_due_date' => $this->license_due_date?->toDateString(),
            'eps_id' => $this->eps_id,
            'pension_fund_id' => $this->pension_fund_id,
            'severance_fund_id' => $this->severance_fund_id,
            'has_social_security' => $this->has_social_security,
            'active' => $this->active,
        ];
    }
}
