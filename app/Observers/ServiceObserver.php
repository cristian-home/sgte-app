<?php

namespace App\Observers;

use App\Enums\DayStatusEnum;
use App\Models\DayStatus;
use App\Models\Service;

class ServiceObserver
{
    public function created(Service $service): void
    {
        $date = $service->service_date;

        $exists = DayStatus::whereDate('date', $date)->exists();

        if (! $exists) {
            DayStatus::create([
                'date' => $date,
                'status' => DayStatusEnum::Projected,
            ]);
        }
    }

    public function deleted(Service $service): void
    {
        $date = $service->service_date;

        $remainingCount = Service::whereDate('service_date', $date)
            ->whereNull('deleted_at')
            ->where('id', '!=', $service->id)
            ->count();

        if ($remainingCount === 0) {
            DayStatus::whereDate('date', $date)->delete();
        }
    }
}
