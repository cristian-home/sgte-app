<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DataImportTemplateController extends Controller
{
    public function show(string $type): BinaryFileResponse
    {
        Gate::authorize(Permission::MANAGE_DATA_IMPORTS->value);

        // URL slug (third-parties) → filename (third_parties.csv).
        $filename = str_replace('-', '_', $type);
        $path = database_path("csv/templates/{$filename}.csv");

        abort_unless(file_exists($path), 404, "Plantilla '{$type}' no encontrada.");

        return response()->download(
            $path,
            "plantilla_{$filename}.csv",
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }
}
