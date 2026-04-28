<?php

namespace App\Http\Requests;

use App\Enums\DataImportType;
use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class DataImportStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows(Permission::MANAGE_DATA_IMPORTS->value);
    }

    public function rules(): array
    {
        $rules = [
            'dry_run' => ['nullable', 'boolean'],
            'update_existing' => ['nullable', 'boolean'],
            'from_import_id' => ['nullable', 'integer', 'exists:data_imports,id'],
        ];

        // type + csv are required only on a fresh upload; on retry-as-real we
        // inherit them from the source import.
        if (! $this->filled('from_import_id')) {
            $rules['type'] = ['required', Rule::enum(DataImportType::class)];
            $rules['csv'] = ['required', 'file', 'max:20480', 'mimes:csv,txt,xlsx'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'type.required' => 'El tipo de carga es obligatorio.',
            'csv.required' => 'Seleccione un archivo para subir.',
            'csv.max' => 'El archivo excede el límite de 20 MB.',
            'csv.mimes' => 'Solo se aceptan archivos CSV o XLSX.',
            'from_import_id.exists' => 'El import de origen no existe.',
        ];
    }
}
