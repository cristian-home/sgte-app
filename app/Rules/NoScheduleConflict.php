<?php

namespace App\Rules;

use App\Models\Service;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;

class NoScheduleConflict implements ValidationRule
{
    public function __construct(
        protected string $field,
        protected int $fieldValue,
        protected string $serviceDate,
        protected string $plannedStartTime,
        protected int $plannedDuration,
        protected ?int $excludeServiceId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $newStart = Carbon::parse($this->serviceDate.' '.$this->plannedStartTime);
        $newEnd = $newStart->copy()->addMinutes($this->plannedDuration);

        $conflicts = Service::query()
            ->where($this->field, $this->fieldValue)
            ->where('service_date', $this->serviceDate)
            ->when($this->excludeServiceId, fn ($q) => $q->where('id', '!=', $this->excludeServiceId))
            ->get(['id', 'planned_start_time', 'planned_duration']);

        foreach ($conflicts as $existing) {
            $existingStart = Carbon::parse($this->serviceDate.' '.$existing->planned_start_time);
            $existingEnd = $existingStart->copy()->addMinutes($existing->planned_duration);

            if ($existingStart < $newEnd && $newStart < $existingEnd) {
                $label = $this->field === 'vehicle_id' ? 'vehiculo' : 'conductor';
                $fail("El {$label} ya tiene un servicio asignado en este horario ({$existingStart->format('H:i')} - {$existingEnd->format('H:i')}).");

                return;
            }
        }
    }
}
