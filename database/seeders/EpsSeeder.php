<?php

namespace Database\Seeders;

use App\Models\Eps;
use Illuminate\Database\Seeder;

class EpsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $entities = [
            ['code' => 'EPS001', 'name' => 'Nueva EPS'],
            ['code' => 'EPS002', 'name' => 'Sura EPS'],
            ['code' => 'EPS003', 'name' => 'Sanitas'],
            ['code' => 'EPS004', 'name' => 'Salud Total'],
            ['code' => 'EPS005', 'name' => 'Coomeva EPS'],
            ['code' => 'EPS006', 'name' => 'Famisanar'],
            ['code' => 'EPS007', 'name' => 'Compensar'],
            ['code' => 'EPS008', 'name' => 'Coosalud'],
        ];

        foreach ($entities as $entity) {
            Eps::firstOrCreate(
                ['code' => $entity['code']],
                $entity,
            );
        }
    }
}
