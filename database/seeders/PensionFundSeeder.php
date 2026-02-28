<?php

namespace Database\Seeders;

use App\Models\PensionFund;
use Illuminate\Database\Seeder;

class PensionFundSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $funds = [
            ['code' => 'FP001', 'name' => 'Porvenir'],
            ['code' => 'FP002', 'name' => 'Proteccion'],
            ['code' => 'FP003', 'name' => 'Colfondos'],
            ['code' => 'FP004', 'name' => 'Old Mutual'],
            ['code' => 'FP005', 'name' => 'Colpensiones'],
        ];

        foreach ($funds as $fund) {
            PensionFund::firstOrCreate(
                ['code' => $fund['code']],
                $fund,
            );
        }
    }
}
