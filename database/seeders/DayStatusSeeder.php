<?php

namespace Database\Seeders;

use App\Enums\DayStatusEnum;
use App\Models\DayStatus;
use App\Models\User;
use Database\Seeders\Support\SeedClock;
use Illuminate\Database\Seeder;

class DayStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Fills ±7 days around today: past days are Executed (with the admin
     * as executor, closed at 20:00 operation time), today and future days
     * are Projected. Mirrors the curated ServiceSeeder window.
     */
    public function run(): void
    {
        $executor = User::query()->where('email', 'admin@sgte.app')->first()
            ?? User::first();

        for ($offset = -7; $offset <= 7; $offset++) {
            $isPast = $offset < 0;

            DayStatus::firstOrCreate(
                ['date' => SeedClock::dateString($offset)],
                [
                    'status' => ($isPast ? DayStatusEnum::Executed : DayStatusEnum::Projected)->value,
                    'executor_id' => $isPast ? $executor?->id : null,
                    'executed_at' => $isPast ? SeedClock::at($offset, '20:00') : null,
                ],
            );
        }
    }
}
