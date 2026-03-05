<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Municipality;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class DepartmentAndMunicipalitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $csvPath = Storage::disk('local')->path('municipalities_data.csv');
        $handle = fopen($csvPath, 'r');

        // Skip the single header row
        fgetcsv($handle, 0, ';');

        $departments = [];

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            if (\count($row) < 7 || empty(trim($row[0]))) {
                continue;
            }

            $deptCode = trim($row[0]);
            $deptName = trim($row[1]);
            $munCode = trim($row[2]);
            $munName = trim($row[3]);
            $type = trim($row[4]);
            $longitude = str_replace(',', '.', trim($row[5]));
            $latitude = str_replace(',', '.', trim($row[6]));

            if (! isset($departments[$deptCode])) {
                $departments[$deptCode] = Department::firstOrCreate(
                    ['code' => $deptCode],
                    ['name' => $deptName],
                );
            }

            Municipality::firstOrCreate(
                ['code' => $munCode],
                [
                    'department_id' => $departments[$deptCode]->id,
                    'name' => $munName,
                    'type' => $type,
                    'latitude' => $latitude !== '' ? $latitude : null,
                    'longitude' => $longitude !== '' ? $longitude : null,
                ],
            );
        }

        fclose($handle);
    }
}
