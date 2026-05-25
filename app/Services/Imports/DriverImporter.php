<?php

namespace App\Services\Imports;

use App\Enums\LicenseCategory;
use App\Enums\Role;
use App\Models\DocumentType;
use App\Models\Driver;
use App\Models\Eps;
use App\Models\Municipality;
use App\Models\PensionFund;
use App\Models\SeveranceFund;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class DriverImporter extends AbstractImporter
{
    public function expectedHeaders(): array
    {
        return [
            'document_type_code',
            'identification_number',
            'first_name',
            'second_name',
            'first_lastname',
            'second_lastname',
            'address',
            'phone',
            'email',
            'license_category',
            'license_due_date',
            'eps_code',
            'pension_fund_code',
            'severance_fund_code',
            'has_social_security',
            'user_email',
            'municipality_code',
        ];
    }

    public function naturalKey(): string
    {
        return 'identification_number';
    }

    public function rules(): array
    {
        $allowedCategories = array_map(fn (LicenseCategory $c) => $c->value, LicenseCategory::cases());

        return [
            'document_type_code' => ['required', 'string', 'exists:document_types,code'],
            'identification_number' => ['required', 'string', 'max:50'],
            'first_name' => ['required', 'string', 'max:100'],
            'second_name' => ['nullable', 'string', 'max:100'],
            'first_lastname' => ['required', 'string', 'max:100'],
            'second_lastname' => ['nullable', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
            'license_category' => ['required', 'string', 'in:'.implode(',', $allowedCategories)],
            'license_due_date' => ['required', 'date_format:Y-m-d'],
            'eps_code' => ['required', 'string', 'exists:eps,code'],
            'pension_fund_code' => ['required', 'string', 'exists:pension_funds,code'],
            'severance_fund_code' => ['required', 'string', 'exists:severance_funds,code'],
            'has_social_security' => ['required', 'boolean'],
            'user_email' => ['nullable', 'email', 'exists:users,email'],
            'municipality_code' => ['nullable', 'string', 'exists:municipalities,code'],
        ];
    }

    public function messages(): array
    {
        return [
            'document_type_code.exists' => 'Tipo de documento no encontrado en el catálogo.',
            'license_category.in' => 'Categoría de licencia inválida. Valores permitidos: '.implode(', ', array_map(fn (LicenseCategory $c) => $c->value, LicenseCategory::cases())).'.',
            'license_due_date.date_format' => 'Fecha de vencimiento de licencia debe estar en formato YYYY-MM-DD.',
            'eps_code.exists' => 'EPS no encontrada en el catálogo.',
            'pension_fund_code.exists' => 'Fondo de pensiones no encontrado en el catálogo.',
            'severance_fund_code.exists' => 'Fondo de cesantías no encontrado en el catálogo.',
            'user_email.exists' => 'Usuario con ese email no existe.',
        ];
    }

    public function transformRow(array $row): array
    {
        $documentType = DocumentType::query()->where('code', $row['document_type_code'])->first();
        if (! $documentType) {
            throw new RowTransformException("Tipo de documento '{$row['document_type_code']}' no encontrado.");
        }

        $eps = Eps::query()->where('code', $row['eps_code'])->first();
        if (! $eps) {
            throw new RowTransformException("EPS '{$row['eps_code']}' no encontrada.");
        }

        $pensionFund = PensionFund::query()->where('code', $row['pension_fund_code'])->first();
        if (! $pensionFund) {
            throw new RowTransformException("Fondo de pensiones '{$row['pension_fund_code']}' no encontrado.");
        }

        $severanceFund = SeveranceFund::query()->where('code', $row['severance_fund_code'])->first();
        if (! $severanceFund) {
            throw new RowTransformException("Fondo de cesantías '{$row['severance_fund_code']}' no encontrado.");
        }

        $municipality = null;
        if (! empty($row['municipality_code'])) {
            $municipality = Municipality::query()->where('code', $row['municipality_code'])->first();
            if (! $municipality) {
                throw new RowTransformException("Municipio '{$row['municipality_code']}' no encontrado.");
            }
        }

        $userId = null;
        if (! empty($row['user_email'])) {
            $user = User::query()->where('email', $row['user_email'])->first();
            if (! $user) {
                throw new RowTransformException("Usuario '{$row['user_email']}' no encontrado.");
            }
            if (! $user->hasRole(Role::DRIVER)) {
                throw new RowTransformException("El usuario '{$row['user_email']}' no tiene rol conductor.");
            }
            $userId = $user->id;
        }

        return [
            'user_id' => $userId,
            'document_type_id' => $documentType->id,
            'identification_number' => $row['identification_number'],
            'first_name' => $row['first_name'],
            'second_name' => $row['second_name'] ?? null,
            'first_lastname' => $row['first_lastname'],
            'second_lastname' => $row['second_lastname'] ?? null,
            'municipality_id' => $municipality?->id,
            'address' => $row['address'],
            'phone' => $row['phone'],
            'email' => $row['email'],
            'license_category' => $row['license_category'],
            'license_due_date' => $row['license_due_date'],
            'eps_id' => $eps->id,
            'pension_fund_id' => $pensionFund->id,
            'severance_fund_id' => $severanceFund->id,
            'has_social_security' => (bool) $row['has_social_security'],
            'active' => true,
        ];
    }

    public function findExisting(string $naturalKeyValue): ?Model
    {
        return Driver::query()->where('identification_number', $naturalKeyValue)->first();
    }

    public function persistNew(array $data): Model
    {
        return Driver::query()->create($data);
    }

    public function applyUpdate(Model $existing, array $data): Model
    {
        $existing->update($data);

        return $existing;
    }
}
