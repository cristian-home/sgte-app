<?php

namespace App\Services\Imports;

use App\Enums\VehicleStatus;
use App\Enums\VehicleType;
use App\Models\Municipality;
use App\Models\ThirdParty;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Model;

class VehicleImporter extends AbstractImporter
{
    public function expectedHeaders(): array
    {
        return [
            'plate',
            'internal_code',
            'mobile_number',
            'type',
            'brand',
            'line',
            'model_year',
            'engine_number',
            'chassis_number',
            'capacity',
            'is_third_party',
            'third_party_identification',
            'soat_due_date',
            'rtm_due_date',
            'operation_card_due_date',
            'municipality_code',
            'timezone',
        ];
    }

    public function naturalKey(): string
    {
        return 'plate';
    }

    public function rules(): array
    {
        $allowedTypes = array_map(fn (VehicleType $t) => $t->value, VehicleType::cases());

        return [
            'plate' => ['required', 'string', 'min:6', 'max:6'],
            'internal_code' => ['required', 'string', 'max:20'],
            'mobile_number' => ['required', 'string', 'max:20'],
            'type' => ['required', 'string', 'in:'.implode(',', $allowedTypes)],
            'brand' => ['required', 'string', 'max:50'],
            'line' => ['required', 'string', 'max:50'],
            'model_year' => ['required', 'integer', 'between:1980,2100'],
            'engine_number' => ['required', 'string', 'max:50'],
            'chassis_number' => ['required', 'string', 'max:50'],
            'capacity' => ['required', 'integer', 'min:1'],
            'is_third_party' => ['required', 'boolean'],
            'third_party_identification' => ['nullable', 'string', 'required_if:is_third_party,1', 'exists:third_parties,identification_number'],
            'soat_due_date' => ['required', 'date_format:Y-m-d'],
            'rtm_due_date' => ['required', 'date_format:Y-m-d'],
            'operation_card_due_date' => ['required', 'date_format:Y-m-d'],
            'municipality_code' => ['nullable', 'string', 'exists:municipalities,code'],
            // Optional. Blank → DB default 'America/Bogota'. Must be a
            // valid IANA timezone identifier.
            'timezone' => ['nullable', 'string', 'in:'.implode(',', timezone_identifiers_list())],
        ];
    }

    public function messages(): array
    {
        return [
            'plate.min' => 'La placa debe tener exactamente 6 caracteres.',
            'plate.max' => 'La placa debe tener exactamente 6 caracteres.',
            'type.in' => 'Tipo de vehículo inválido. Valores permitidos: '.implode(', ', array_map(fn (VehicleType $t) => $t->value, VehicleType::cases())).'.',
            'third_party_identification.required_if' => 'La identificación del tercero es obligatoria cuando is_third_party=1.',
            'third_party_identification.exists' => 'No existe un tercero con esa identificación.',
            'municipality_code.exists' => 'El municipio no existe en el catálogo DIVIPOLA.',
            'timezone.in' => 'Zona horaria inválida. Use un identificador IANA (ej. America/Bogota) o deje en blanco para el valor por defecto.',
        ];
    }

    public function transformRow(array $row): array
    {
        $thirdPartyId = null;
        if ((bool) $row['is_third_party']) {
            $thirdParty = ThirdParty::query()
                ->where('identification_number', $row['third_party_identification'])
                ->first();
            if (! $thirdParty) {
                throw new RowTransformException(
                    "Tercero con identificación '{$row['third_party_identification']}' no encontrado."
                );
            }
            $thirdPartyId = $thirdParty->id;
        }

        $municipality = null;
        if (! empty($row['municipality_code'])) {
            $municipality = Municipality::query()->where('code', $row['municipality_code'])->first();
            if (! $municipality) {
                throw new RowTransformException("Municipio '{$row['municipality_code']}' no encontrado.");
            }
        }

        $payload = [
            'plate' => strtoupper((string) $row['plate']),
            'internal_code' => $row['internal_code'],
            'mobile_number' => $row['mobile_number'],
            'type' => $row['type'],
            'brand' => $row['brand'],
            'line' => $row['line'],
            'model_year' => (int) $row['model_year'],
            'engine_number' => $row['engine_number'],
            'chassis_number' => $row['chassis_number'],
            'capacity' => (int) $row['capacity'],
            'is_third_party' => (bool) $row['is_third_party'],
            'third_party_id' => $thirdPartyId,
            'soat_due_date' => $row['soat_due_date'],
            'rtm_due_date' => $row['rtm_due_date'],
            'operation_card_due_date' => $row['operation_card_due_date'],
            'municipality_id' => $municipality?->id,
            'status' => VehicleStatus::Active->value,
        ];

        // Only set timezone when explicitly provided; otherwise omit so
        // the DB default ('America/Bogota') applies on insert and the
        // current value is preserved on update.
        if (! empty($row['timezone'])) {
            $payload['timezone'] = $row['timezone'];
        }

        return $payload;
    }

    public function findExisting(string $naturalKeyValue): ?Model
    {
        return Vehicle::query()->where('plate', strtoupper($naturalKeyValue))->first();
    }

    public function persistNew(array $data): Model
    {
        return Vehicle::query()->create($data);
    }

    public function applyUpdate(Model $existing, array $data): Model
    {
        $existing->update($data);

        return $existing;
    }
}
