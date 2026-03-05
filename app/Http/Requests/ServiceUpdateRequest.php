<?php

namespace App\Http\Requests;

use App\Enums\DayStatusEnum;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Models\DayStatus;
use Illuminate\Validation\Rule;

class ServiceUpdateRequest extends ServiceStoreRequest
{
    protected function excludeServiceId(): ?int
    {
        return $this->route('service')->id;
    }

    public function authorize(): bool
    {
        $service = $this->route('service');
        $dayStatus = DayStatus::where('date', $service->service_date->format('Y-m-d'))->first();

        if ($dayStatus?->status === DayStatusEnum::Executed) {
            $user = $this->user();

            if ($user->hasAnyRole([Role::ADMIN, Role::SUPER_ADMIN])) {
                return true;
            }

            if ($user->can('services.update-executed')) {
                return true;
            }

            return false;
        }

        return true;
    }

    public function rules(): array
    {
        $service = $this->route('service');
        $dayStatus = DayStatus::where('date', $service->service_date->format('Y-m-d'))->first();

        if ($dayStatus?->status !== DayStatusEnum::Executed) {
            return parent::rules();
        }

        $user = $this->user();

        if ($user->hasAnyRole([Role::ADMIN, Role::SUPER_ADMIN])) {
            $rules = parent::rules();
            $rules['justification'] = ['required', 'string', 'min:10', 'max:500'];

            return $rules;
        }

        // Accounting: billing fields only
        return [
            'billing_group' => ['nullable', 'string', 'max:50'],
            'unit_value' => ['required', 'numeric', 'between:-9999999999.99,9999999999.99'],
            'quantity' => ['required', 'integer'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
        ];
    }

    public function after(): array
    {
        $service = $this->route('service');
        $dayStatus = DayStatus::where('date', $service->service_date->format('Y-m-d'))->first();

        if ($dayStatus?->status === DayStatusEnum::Executed) {
            $user = $this->user();

            if (! $user->hasAnyRole([Role::ADMIN, Role::SUPER_ADMIN])) {
                return [];
            }
        }

        return parent::after();
    }
}
