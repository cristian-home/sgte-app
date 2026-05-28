<?php

namespace App\Services\Imports;

use App\Enums\BillingUnitType;
use App\Enums\ContractObject;
use App\Models\Contract;
use App\Models\DocumentType;
use App\Models\Municipality;
use App\Models\ThirdParty;
use App\Support\Tz;
use Illuminate\Database\Eloquent\Model;

class ThirdPartyImporter extends AbstractImporter
{
    public function expectedHeaders(): array
    {
        return [
            'document_type_code',
            'identification_number',
            'is_natural_person',
            'first_name',
            'second_name',
            'first_lastname',
            'second_lastname',
            'company_name',
            'trade_name',
            'address',
            'phone',
            'email',
            'is_customer',
            'is_provider',
            'municipality_code',
        ];
    }

    public function naturalKey(): string
    {
        return 'identification_number';
    }

    public function rules(): array
    {
        return [
            'document_type_code' => ['required', 'string', 'exists:document_types,code'],
            'identification_number' => ['required', 'string', 'max:50'],
            'is_natural_person' => ['required', 'boolean'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'second_name' => ['nullable', 'string', 'max:100'],
            'first_lastname' => ['nullable', 'string', 'max:100'],
            'second_lastname' => ['nullable', 'string', 'max:100'],
            'company_name' => ['nullable', 'string', 'max:200'],
            'trade_name' => ['nullable', 'string', 'max:200'],
            'address' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
            'is_customer' => ['required', 'boolean'],
            'is_provider' => ['required', 'boolean'],
            'municipality_code' => ['nullable', 'string', 'exists:municipalities,code'],
        ];
    }

    public function messages(): array
    {
        return [
            'document_type_code.exists' => 'El tipo de documento no existe en el catálogo.',
            'municipality_code.exists' => 'El municipio no existe en el catálogo DIVIPOLA.',
            'is_customer.required' => 'Indique si el tercero es cliente (1/0).',
            'is_provider.required' => 'Indique si el tercero es proveedor (1/0).',
        ];
    }

    public function transformRow(array $row): array
    {
        $documentType = DocumentType::query()->where('code', $row['document_type_code'])->first();
        if (! $documentType) {
            throw new RowTransformException("Tipo de documento '{$row['document_type_code']}' no encontrado.");
        }

        $municipality = null;
        if (! empty($row['municipality_code'])) {
            $municipality = Municipality::query()->where('code', $row['municipality_code'])->first();
            if (! $municipality) {
                throw new RowTransformException("Municipio '{$row['municipality_code']}' no encontrado.");
            }
        }

        return [
            'document_type_id' => $documentType->id,
            'identification_number' => $row['identification_number'],
            'is_natural_person' => (bool) $row['is_natural_person'],
            'first_name' => $row['first_name'] ?? null,
            'second_name' => $row['second_name'] ?? null,
            'first_lastname' => $row['first_lastname'] ?? null,
            'second_lastname' => $row['second_lastname'] ?? null,
            'company_name' => $row['company_name'] ?? null,
            'trade_name' => $row['trade_name'] ?? null,
            'address' => $row['address'],
            'phone' => $row['phone'],
            'email' => $row['email'],
            'is_customer' => (bool) $row['is_customer'],
            'is_provider' => (bool) $row['is_provider'],
            'municipality_id' => $municipality?->id,
            'active' => true,
        ];
    }

    public function findExisting(string $naturalKeyValue): ?Model
    {
        return ThirdParty::query()->where('identification_number', $naturalKeyValue)->first();
    }

    public function persistNew(array $data): Model
    {
        $thirdParty = ThirdParty::query()->create($data);

        if ($thirdParty->is_customer) {
            $this->ensureGenericContractFor($thirdParty);
        }

        return $thirdParty;
    }

    /**
     * Create the bulk-import default contract for a newly-imported customer.
     *
     * The contract is generic, active, runs from today through the end of
     * 2026-12-31 (operation TZ), and is unique per third party — running the
     * import twice does not create a second one.
     *
     * Numbering reuses the `GEN-NNNN-YYYY` pattern from
     * {@see \App\Http\Controllers\ContractController::store()}. The
     * `count()+1` lookup is non-atomic but the importer runs serially in a
     * single queued job, so a race is not reachable today.
     */
    protected function ensureGenericContractFor(ThirdParty $thirdParty): void
    {
        $tz = Tz::operation();
        $today = Tz::nowIn($tz)->format('Y-m-d');

        Contract::firstOrCreate(
            [
                'third_party_id' => $thirdParty->id,
                'is_generic' => true,
                'active' => true,
            ],
            [
                'contract_number' => $this->nextGenericContractNumber(),
                'contract_object' => ContractObject::Occasional->value,
                'timezone' => $tz,
                'start_at' => Tz::startOfDayInTzAsUtc($today, $tz),
                'end_at' => Tz::endOfDayInTzAsUtc('2026-12-31', $tz),
                'route_description' => 'Contrato genérico — '.$this->thirdPartyDisplayName($thirdParty),
                'billing_unit_type' => BillingUnitType::Viaje->value,
            ],
        );
    }

    protected function nextGenericContractNumber(): string
    {
        $year = now()->year;
        $sequence = Contract::query()
            ->where('contract_number', 'like', "GEN-%-{$year}")
            ->count() + 1;

        return sprintf('GEN-%04d-%d', $sequence, $year);
    }

    protected function thirdPartyDisplayName(ThirdParty $thirdParty): string
    {
        if (! empty($thirdParty->company_name)) {
            return $thirdParty->company_name;
        }

        $name = trim(($thirdParty->first_name ?? '').' '.($thirdParty->first_lastname ?? ''));

        return $name !== '' ? $name : $thirdParty->identification_number;
    }

    public function applyUpdate(Model $existing, array $data): Model
    {
        $existing->update($data);

        return $existing;
    }
}
