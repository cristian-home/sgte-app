<?php

namespace App\Http\Requests;

use App\Enums\DayStatusEnum;
use App\Enums\PaymentMethod;
use App\Enums\ServiceStatus;
use App\Models\Contract;
use App\Models\DayStatus;
use App\Models\Vehicle;
use App\Rules\NoScheduleConflict;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServiceStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [
            'contract_id' => ['required', 'integer', 'exists:contracts,id'],
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'driver_id' => ['nullable', 'integer', 'exists:drivers,id'],
            'invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
            'service_date' => ['required', 'date'],
            'origin_municipality_id' => ['nullable', 'integer', 'exists:municipalities,id'],
            'origin_address' => ['nullable', 'string', 'max:255'],
            'origin_coordinates' => ['nullable', 'string', 'max:50'],
            'destination_municipality_id' => ['nullable', 'integer', 'exists:municipalities,id'],
            'destination_address' => ['nullable', 'string', 'max:255'],
            'destination_coordinates' => ['nullable', 'string', 'max:50'],
            'planned_start_time' => ['required'],
            'planned_duration' => ['required', 'integer'],
            'actual_start_time' => ['nullable', Rule::requiredIf($this->input('service_status') === 'closed')],
            'actual_end_time' => ['nullable', Rule::requiredIf($this->input('service_status') === 'closed'), 'after:actual_start_time'],
            'unit_value' => ['required', 'numeric', 'between:-9999999999.99,9999999999.99'],
            'quantity' => ['required', 'integer'],
            'billing_group' => ['nullable', 'string', 'max:50'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'service_status' => ['required', Rule::enum(ServiceStatus::class)],
        ];

        if ($this->filled('vehicle_id') && $this->filled('service_date') && $this->filled('planned_start_time') && $this->filled('planned_duration')) {
            $rules['vehicle_id'][] = new NoScheduleConflict(
                'vehicle_id',
                (int) $this->input('vehicle_id'),
                $this->input('service_date'),
                $this->input('planned_start_time'),
                (int) $this->input('planned_duration'),
                $this->excludeServiceId(),
            );
        }

        if ($this->filled('driver_id') && $this->filled('service_date') && $this->filled('planned_start_time') && $this->filled('planned_duration')) {
            $rules['driver_id'][] = new NoScheduleConflict(
                'driver_id',
                (int) $this->input('driver_id'),
                $this->input('service_date'),
                $this->input('planned_start_time'),
                (int) $this->input('planned_duration'),
                $this->excludeServiceId(),
            );
        }

        return $rules;
    }

    /**
     * Additional validation after standard rules pass.
     */
    public function after(): array
    {
        return [
            function ($validator): void {
                $this->validateExecutedDayRestriction($validator);
                $this->validateContractCoversDate($validator);
                $this->validateDriverRequired($validator);
            },
        ];
    }

    protected function validateExecutedDayRestriction($validator): void
    {
        if (! $this->filled('service_date')) {
            return;
        }

        $dayStatus = DayStatus::where('date', $this->input('service_date'))->first();

        if ($dayStatus?->status === DayStatusEnum::Executed) {
            $validator->errors()->add('service_date', 'No se pueden crear servicios en un día ejecutado.');
        }
    }

    protected function validateContractCoversDate($validator): void
    {
        if (! $this->filled('contract_id') || ! $this->filled('service_date')) {
            return;
        }

        $contract = Contract::find($this->input('contract_id'));

        if (! $contract) {
            return;
        }

        if (! $contract->active) {
            $validator->errors()->add('contract_id', 'El contrato seleccionado no esta activo.');
        }

        $serviceDate = $this->input('service_date');

        $startDate = $contract->start_date instanceof \Illuminate\Support\Carbon ? $contract->start_date->toDateString() : (string) $contract->start_date;
        $endDate = $contract->end_date instanceof \Illuminate\Support\Carbon ? $contract->end_date->toDateString() : (string) $contract->end_date;

        if ($serviceDate < $startDate || $serviceDate > $endDate) {
            $validator->errors()->add('contract_id', 'La fecha del servicio no esta dentro del rango del contrato.');
        }
    }

    protected function validateDriverRequired($validator): void
    {
        if (! $this->filled('vehicle_id')) {
            return;
        }

        $vehicle = Vehicle::find($this->input('vehicle_id'));

        if (! $vehicle) {
            return;
        }

        if (! $vehicle->is_third_party && ! $this->filled('driver_id')) {
            $validator->errors()->add('driver_id', 'El conductor es requerido para vehiculos propios.');
        }
    }

    protected function excludeServiceId(): ?int
    {
        return null;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('vehicle_id')) {
            $vehicle = Vehicle::find($this->input('vehicle_id'));

            if ($vehicle && $vehicle->is_third_party) {
                $this->merge(['driver_id' => null]);
            }
        }
    }
}
