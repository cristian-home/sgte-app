<?php

namespace Database\Seeders;

use App\Models\SeveranceFund;
use Illuminate\Database\Seeder;

class SeveranceFundSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $funds = [
            ['code' => 'FC001', 'name' => 'Porvenir'],
            ['code' => 'FC002', 'name' => 'Proteccion'],
            ['code' => 'FC003', 'name' => 'Colfondos'],
            ['code' => 'FC004', 'name' => 'FNA'],
        ];

        foreach ($funds as $fund) {
            SeveranceFund::firstOrCreate(
                ['code' => $fund['code']],
                $fund,
            );
        }
    }
}
