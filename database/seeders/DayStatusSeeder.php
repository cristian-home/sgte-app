<?php

namespace Database\Seeders;

use App\Enums\DayStatusEnum;
use App\Models\DayStatus;
use App\Models\User;
use Illuminate\Database\Seeder;

class DayStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $executor = User::first();

        $dayStatuses = [
            [
                'date' => '2026-02-24',
                'status' => DayStatusEnum::Executed->value,
                'executor_id' => $executor?->id,
                'executed_at' => '2026-02-24 18:00:00',
            ],
            [
                'date' => '2026-02-25',
                'status' => DayStatusEnum::Executed->value,
                'executor_id' => $executor?->id,
                'executed_at' => '2026-02-25 17:30:00',
            ],
            [
                'date' => '2026-02-26',
                'status' => DayStatusEnum::Executed->value,
                'executor_id' => $executor?->id,
                'executed_at' => '2026-02-26 18:15:00',
            ],
            [
                'date' => '2026-02-27',
                'status' => DayStatusEnum::Projected->value,
                'executor_id' => null,
                'executed_at' => null,
            ],
            [
                'date' => '2026-02-28',
                'status' => DayStatusEnum::Projected->value,
                'executor_id' => null,
                'executed_at' => null,
            ],
        ];

        foreach ($dayStatuses as $ds) {
            DayStatus::firstOrCreate(
                ['date' => $ds['date']],
                $ds,
            );
        }
    }
}
