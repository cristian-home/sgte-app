<?php

namespace App\Observers;

use App\Enums\DayStatusEnum;
use App\Models\DayStatus;
use App\Models\Service;

class ServiceObserver
{
    public function created(Service $service): void
    {
        $date = $service->service_date->format('Y-m-d');

        DayStatus::firstOrCreate(
            ['date' => $date],
            ['status' => DayStatusEnum::Projected],
        );
    }

    public function deleted(Service $service): void
    {
        $date = $service->service_date->format('Y-m-d');

        $remainingCount = Service::where('service_date', $date)
            ->whereNull('deleted_at')
            ->where('id', '!=', $service->id)
            ->count();

        if ($remainingCount === 0) {
            DayStatus::where('date', $date)->delete();
        }
    }
}
