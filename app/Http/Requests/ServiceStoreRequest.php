<?php

namespace App\Http\Requests;

use App\Enums\DayStatusEnum;
use App\Enums\LicenseCategory;
use App\Enums\PaymentMethod;
use App\Enums\Permission;
use App\Enums\ServiceStatus;
use App\Enums\VehicleType;
use App\Models\Contract;
use App\Models\DayStatus;
use App\Models\Driver;
use App\Models\Vehicle;
use App\Rules\NoScheduleConflict;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ServiceStoreRequest extends FormRequest
{
    /**
     * License ↔ vehicle-type compatibility for Colombian public passenger transport.
     * Keys are vehicle types, values are the license categories legally authorized
     * to drive them for public passenger transport.
     *
     * @var array<string, list<string>>
     */
    protected const LICENSE_CATEGORY_MAP = [
        VehicleType::Bus->value => [LicenseCategory::C2->value, LicenseCategory::C3->value],
        VehicleType::Buseta->value => [LicenseCategory::C2->value, LicenseCategory::C3->value],
        VehicleType::Van->value => [LicenseCategory::C1->value, LicenseCategory::C2->value, LicenseCategory::C3->value],
        VehicleType::Automobile->value => [LicenseCategory::C1->value, LicenseCategory::C2->value, LicenseCategory::C3->value],
    ];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows(Permission::CREATE_SERVICES->value);
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
            // Wall-clock helpers retained for the form payload; the
            // persisted source of truth is `planned_start_at` (UTC instant)
            // and `service_date_local` (operation-TZ day), merged in
            // prepareForValidation().
            'service_date' => ['required', 'date_format:Y-m-d'],
            'service_date_local' => ['required', 'date_format:Y-m-d'],
            'planned_start_time' => ['required', 'date_format:H:i'],
            'timezone' => ['required', 'string', Rule::in(timezone_identifiers_list())],
            'planned_start_at' => ['required', 'date'],
            'origin_municipality_id' => ['nullable', 'integer', 'exists:municipalities,id'],
            'origin_address' => ['nullable', 'string', 'max:255'],
            'origin_coordinates' => ['nullable', 'string', 'max:50'],
            'destination_municipality_id' => ['nullable', 'integer', 'exists:municipalities,id'],
            'destination_address' => ['nullable', 'string', 'max:255'],
            'destination_coordinates' => ['nullable', 'string', 'max:50'],
            'planned_duration' => ['required', 'integer'],
            'actual_start_time' => ['nullable', Rule::requiredIf($this->input('service_status') === 'closed')],
            'actual_end_time' => ['nullable', Rule::requiredIf($this->input('service_status') === 'closed'), 'after:actual_start_time'],
            'actual_start_at' => ['nullable', 'date'],
            'actual_end_at' => ['nullable', 'date', 'after:actual_start_at'],
            'unit_value' => ['required', 'numeric', 'between:-9999999999.99,9999999999.99'],
            'quantity' => ['required', 'integer'],
            'billing_group' => ['nullable', 'string', 'max:50'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'service_status' => ['required', Rule::enum(ServiceStatus::class)],
            'manual_entry_justification' => ['nullable', 'string', 'min:10', 'max:500'],
        ];

        if ($this->filled('vehicle_id') && $this->filled('planned_start_at') && $this->filled('planned_duration')) {
            $rules['vehicle_id'][] = new NoScheduleConflict(
                'vehicle_id',
                (int) $this->input('vehicle_id'),
                $this->input('planned_start_at'),
                (int) $this->input('planned_duration'),
                $this->excludeServiceId(),
            );
        }

        if ($this->filled('driver_id') && $this->filled('planned_start_at') && $this->filled('planned_duration')) {
            $rules['driver_id'][] = new NoScheduleConflict(
                'driver_id',
                (int) $this->input('driver_id'),
                $this->input('planned_start_at'),
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
                $this->validateVehicleDocumentsNotExpired($validator);
                $this->validateDriverLicense($validator);
                $this->validateRetroactiveEntry($validator);
            },
        ];
    }

    /**
     * REQ-009 provenance gate on create:
     *
     * - If service_date >= today AND service_status = closed, reject.
     *   A service that hasn't happened yet cannot be Cerrado; the
     *   driver workflow (confirmStart → confirmEnd) is the only legit
     *   path to close.
     * - If service_date < today AND service_status = closed, accept
     *   the create but require manual_entry_justification (the Store
     *   request already enforces min:10). This distinguishes a back-
     *   filled historical record from a shortcut around the driver
     *   workflow; the controller tags the activity_log entry with
     *   source=retroactive_entry so /audit-log can filter it.
     *
     * Only runs on create — ServiceUpdateRequest inherits rules() but
     * overrides after() and never calls this method.
     */
    protected function validateRetroactiveEntry($validator): void
    {
        // Update flows never go through this gate. Edits to an existing
        // service — including flipping status to Closed on an open past-
        // date row — are governed by ServiceUpdateRequest and the
        // executed-day justification already in place there.
        if ($this->route('service') !== null) {
            return;
        }

        if (! $this->filled('service_date_local') || ! $this->filled('service_status')) {
            return;
        }

        $status = $this->input('service_status');
        if ($status !== ServiceStatus::Closed->value) {
            return;
        }

        $today = Carbon::now($this->resolveServiceTimezone())->toDateString();
        $serviceDateString = (string) $this->input('service_date_local');

        if ($serviceDateString >= $today) {
            $validator->errors()->add(
                'service_status',
                'Un servicio con fecha hoy o futura no puede crearse en estado Cerrado. Use el flujo del conductor para cerrar servicios ejecutados.',
            );

            return;
        }

        $justification = trim((string) $this->input('manual_entry_justification'));
        if ($justification === '') {
            $validator->errors()->add(
                'manual_entry_justification',
                'La justificación es obligatoria al registrar retroactivamente un servicio cerrado.',
            );
        }
    }

    protected function validateExecutedDayRestriction($validator): void
    {
        if (! $this->filled('service_date_local')) {
            return;
        }

        $dayStatus = DayStatus::whereDate('date', $this->input('service_date_local'))->first();

        if ($dayStatus?->status === DayStatusEnum::Executed) {
            $validator->errors()->add('service_date', 'No se pueden crear servicios en un día ejecutado.');
        }
    }

    protected function validateContractCoversDate($validator): void
    {
        if (! $this->filled('contract_id') || ! $this->filled('service_date_local')) {
            return;
        }

        $contract = Contract::find($this->input('contract_id'));

        if (! $contract) {
            return;
        }

        if (! $contract->active) {
            $validator->errors()->add('contract_id', 'El contrato seleccionado no esta activo.');
        }

        $serviceDate = (string) $this->input('service_date_local');

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

    /**
     * REQ-004 AC 3-5: block scheduling a vehicle whose SOAT, RTM, or
     * tarjeta de operación is expired on the service date.
     */
    protected function validateVehicleDocumentsNotExpired($validator): void
    {
        if (! $this->filled('vehicle_id') || ! $this->filled('service_date_local')) {
            return;
        }

        $vehicle = Vehicle::find($this->input('vehicle_id'));

        if (! $vehicle) {
            return;
        }

        $serviceDate = (string) $this->input('service_date_local');
        $documents = [
            'soat_due_date' => 'SOAT',
            'rtm_due_date' => 'RTM',
            'operation_card_due_date' => 'Tarjeta de Operación',
        ];

        foreach ($documents as $column => $label) {
            $dueDate = $vehicle->{$column};

            if ($dueDate === null) {
                $validator->errors()->add(
                    'vehicle_id',
                    "El vehiculo no tiene registrado el {$label}."
                );

                continue;
            }

            $dueDateString = $dueDate instanceof \Illuminate\Support\Carbon ? $dueDate->toDateString() : (string) $dueDate;

            if ($dueDateString < $serviceDate) {
                $validator->errors()->add(
                    'vehicle_id',
                    "El {$label} del vehiculo esta vencido ({$dueDateString})."
                );
            }
        }
    }

    /**
     * REQ-003 AC 5 / REQ-005 AC 2: block scheduling a driver whose
     * license is expired, whose category is incompatible with the
     * vehicle type, or who lacks active social security.
     *
     * Skipped when the vehicle is third-party (driver_id is nulled in
     * prepareForValidation for those).
     */
    protected function validateDriverLicense($validator): void
    {
        if (! $this->filled('driver_id') || ! $this->filled('service_date_local')) {
            return;
        }

        $driver = Driver::find($this->input('driver_id'));

        if (! $driver) {
            return;
        }

        $serviceDate = (string) $this->input('service_date_local');

        if ($driver->license_due_date === null) {
            $validator->errors()->add('driver_id', 'El conductor no tiene registrada la fecha de vencimiento de la licencia.');
        } else {
            $licenseDueString = $driver->license_due_date instanceof \Illuminate\Support\Carbon
                ? $driver->license_due_date->toDateString()
                : (string) $driver->license_due_date;

            if ($licenseDueString < $serviceDate) {
                $validator->errors()->add(
                    'driver_id',
                    "La licencia del conductor esta vencida ({$licenseDueString})."
                );
            }
        }

        if ($driver->has_social_security === false) {
            $validator->errors()->add('driver_id', 'El conductor no tiene seguridad social activa.');
        }

        if ($this->filled('vehicle_id') && $driver->license_category !== null) {
            $vehicle = Vehicle::find($this->input('vehicle_id'));

            if ($vehicle && $vehicle->type !== null) {
                $vehicleType = $vehicle->type instanceof VehicleType ? $vehicle->type->value : (string) $vehicle->type;
                $allowed = self::LICENSE_CATEGORY_MAP[$vehicleType] ?? [];
                $driverCategory = $driver->license_category instanceof LicenseCategory
                    ? $driver->license_category->value
                    : (string) $driver->license_category;

                if ($allowed !== [] && ! in_array($driverCategory, $allowed, true)) {
                    $validator->errors()->add(
                        'driver_id',
                        "La categoria de licencia {$driverCategory} del conductor no es compatible con el tipo de vehiculo."
                    );
                }
            }
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

        $timezone = $this->resolveServiceTimezone();
        $this->merge(['timezone' => $timezone]);

        $serviceDate = $this->input('service_date');
        $plannedTime = $this->input('planned_start_time');

        // Project the wall-clock day + time-of-day in the service's TZ to a
        // UTC instant. This mirrors the iCalendar "instant + IANA TZ"
        // pattern: persistence is universal, presentation is event-TZ.
        if (is_string($serviceDate) && is_string($plannedTime)) {
            $serviceDate = substr($serviceDate, 0, 10);
            $plannedTime = substr($plannedTime, 0, 5);

            try {
                $plannedStartAt = CarbonImmutable::createFromFormat(
                    'Y-m-d H:i',
                    "{$serviceDate} {$plannedTime}",
                    $timezone,
                );

                if ($plannedStartAt !== false) {
                    $this->merge([
                        'planned_start_at' => $plannedStartAt->utc()->toIso8601String(),
                        'service_date_local' => $serviceDate,
                    ]);
                }
            } catch (\Exception $e) {
                // Wall-clock parsing failed; per-field validation rules will
                // surface a clearer error to the user.
            }
        }

        $this->mergeActualInstantsIfPresent($timezone);
    }

    /**
     * Project optional wall-clock actual_*_time inputs into UTC instants.
     */
    protected function mergeActualInstantsIfPresent(string $timezone): void
    {
        $serviceDate = (string) $this->input('service_date');
        if ($serviceDate === '') {
            return;
        }
        $serviceDate = substr($serviceDate, 0, 10);

        foreach (['actual_start_time' => 'actual_start_at', 'actual_end_time' => 'actual_end_at'] as $wallclock => $instant) {
            $time = $this->input($wallclock);
            if (! is_string($time) || $time === '') {
                $this->merge([$instant => null]);

                continue;
            }
            $time = substr($time, 0, 5);

            try {
                $value = CarbonImmutable::createFromFormat(
                    'Y-m-d H:i',
                    "{$serviceDate} {$time}",
                    $timezone,
                );
                if ($value !== false) {
                    $this->merge([$instant => $value->utc()->toIso8601String()]);
                }
            } catch (\Exception $e) {
                // Surface to per-field rule.
            }
        }
    }

    /**
     * Resolve the service's IANA timezone in priority order:
     * request body → selected contract → app operation TZ.
     */
    protected function resolveServiceTimezone(): string
    {
        $requested = $this->input('timezone');
        if (is_string($requested) && $requested !== '' && in_array($requested, timezone_identifiers_list(), true)) {
            return $requested;
        }

        if ($this->filled('contract_id')) {
            $contract = Contract::find($this->input('contract_id'));
            $contractTz = is_object($contract) && property_exists($contract, 'timezone') ? $contract->timezone : null;
            if (is_string($contractTz) && $contractTz !== '') {
                return $contractTz;
            }
        }

        return (string) config('app.operation_tz', 'America/Bogota');
    }
}
