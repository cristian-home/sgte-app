<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\Department;
use App\Models\DocumentType;
use App\Models\Eps;
use App\Models\IncidentType;
use App\Models\Municipality;
use App\Models\PensionFund;
use App\Models\SeveranceFund;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataImportReferenceController extends Controller
{
    public function show(string $catalog): StreamedResponse
    {
        Gate::authorize(Permission::MANAGE_DATA_IMPORTS->value);

        [$builder, $columns, $filename] = match ($catalog) {
            'eps' => [Eps::query()->orderBy('code'), ['code', 'name'], 'eps.csv'],
            'pension-funds' => [PensionFund::query()->orderBy('code'), ['code', 'name'], 'pension_funds.csv'],
            'severance-funds' => [SeveranceFund::query()->orderBy('code'), ['code', 'name'], 'severance_funds.csv'],
            'municipalities' => [
                Municipality::query()->with('department:id,code')->orderBy('code'),
                ['code', 'name', 'department_code'],
                'municipalities.csv',
            ],
            'departments' => [Department::query()->orderBy('code'), ['code', 'name'], 'departments.csv'],
            'document-types' => [DocumentType::query()->orderBy('code'), ['code', 'name'], 'document_types.csv'],
            'incident-types' => [IncidentType::query()->orderBy('code'), ['code', 'name'], 'incident_types.csv'],
            default => abort(404, "Catálogo '{$catalog}' no encontrado."),
        };

        return response()->streamDownload(function () use ($builder, $columns) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);

            $builder->lazy()->each(function ($row) use ($handle, $columns) {
                $line = [];
                foreach ($columns as $column) {
                    $line[] = match ($column) {
                        'department_code' => $row->department?->code ?? '',
                        default => (string) ($row->{$column} ?? ''),
                    };
                }
                fputcsv($handle, $line);
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
