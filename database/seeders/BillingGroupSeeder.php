<?php

namespace Database\Seeders;

use App\Models\BillingGroup;
use Illuminate\Database\Seeder;

class BillingGroupSeeder extends Seeder
{
    /**
     * Five default billing groups that ship with a fresh install. The
     * migration `2026_05_27_163423_migrate_services_billing_groups_to_pivot`
     * inserts the same rows during the JSON-to-pivot transition, so this
     * seeder is also idempotent via `firstOrCreate` keyed on `code`.
     */
    public function run(): void
    {
        $defaults = [
            ['code' => 'salud', 'name' => 'Salud'],
            ['code' => 'escolar', 'name' => 'Escolar'],
            ['code' => 'turismo', 'name' => 'Turismo'],
            ['code' => 'empresarial', 'name' => 'Empresarial'],
            ['code' => 'ocasional', 'name' => 'Ocasional'],
        ];

        foreach ($defaults as $row) {
            BillingGroup::query()->firstOrCreate(
                ['code' => $row['code']],
                ['name' => $row['name'], 'active' => true],
            );
        }
    }
}
