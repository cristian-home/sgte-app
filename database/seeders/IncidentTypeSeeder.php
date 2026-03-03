<?php

namespace Database\Seeders;

use App\Models\IncidentType;
use Illuminate\Database\Seeder;

class IncidentTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            ['code' => 'DELAY', 'name' => 'Retraso', 'severity' => 'minor', 'affects_billing_default' => false],
            ['code' => 'ACCIDENT', 'name' => 'Accidente', 'severity' => 'major', 'affects_billing_default' => true],
            ['code' => 'BREAKDOWN', 'name' => 'Avería', 'severity' => 'major', 'affects_billing_default' => true],
            ['code' => 'TRAFFIC', 'name' => 'Tráfico', 'severity' => 'informational', 'affects_billing_default' => false],
            ['code' => 'WEATHER', 'name' => 'Clima', 'severity' => 'minor', 'affects_billing_default' => false],
            ['code' => 'NO_SHOW', 'name' => 'Cliente No Presentado', 'severity' => 'minor', 'affects_billing_default' => true],
            ['code' => 'OTHER', 'name' => 'Otro', 'severity' => 'informational', 'affects_billing_default' => false],
        ];

        foreach ($types as $type) {
            IncidentType::firstOrCreate(
                ['code' => $type['code']],
                $type,
            );
        }
    }
}
