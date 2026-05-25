<?php

namespace App\Observers;

use App\Enums\DayStatusEnum;
use App\Models\DayStatus;
use App\Models\Service;

class ServiceObserver
{
    public function created(Service $service): void
    {
        $date = $this->dateString($service);

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
        $date = $this->dateString($service);

        $remainingCount = Service::whereDate('service_date_local', $date)
            ->whereNull('deleted_at')
            ->where('id', '!=', $service->id)
            ->count();

        if ($remainingCount === 0) {
            DayStatus::whereDate('date', $date)->delete();
        }
    }

    protected function dateString(Service $service): string
    {
        $value = $service->service_date_local;

        return $value instanceof \DateTimeInterface
            ? $value->format('Y-m-d')
            : (string) $value;
    }
}
