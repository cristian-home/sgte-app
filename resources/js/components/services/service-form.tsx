import { usePage } from '@inertiajs/react';
import { AlertTriangle, Info, Lock, ShieldAlert } from 'lucide-react';
import { useMemo } from 'react';
import InputError from '@/components/input-error';
import MunicipalityCombobox, {
    type MunicipalityOption,
} from '@/components/municipality-combobox';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { PaymentMethod, PaymentMethodLabel } from '@/enums/PaymentMethod';
import { ServiceStatus, ServiceStatusLabel } from '@/enums/ServiceStatus';
import type { DayStatus } from '@/types/models';

export interface VehicleOption {
    id: number;
    plate: string;
    is_third_party: boolean;
    third_party_id: number | null;
    third_party?: ThirdPartyOption | null;
}

export interface ThirdPartyOption {
    id: number;
    identification_number: string;
    first_name: string | null;
    first_lastname: string | null;
    company_name: string | null;
    is_natural_person: boolean;
}

export interface DriverOption {
    id: number;
    first_name: string;
    first_lastname: string;
    identification_number: string;
    license_due_at: string | null;
    license_due_date: string | null;
    timezone?: string | null;
    eps_id: number | null;
    pension_fund_id: number | null;
}

export interface ContractOption {
    id: number;
    contract_number: string;
    third_party_id: number;
    contract_object: string;
    /** UTC instant marking start of contract in the contract's timezone. */
    start_at: string;
    /** Half-open UTC instant: contract is active when service.planned_start_at < end_at. */
    end_at: string;
    timezone: string;
    is_generic: boolean;
    billing_unit_type: string | null;
    third_party?: ThirdPartyOption | null;
}

export interface ServiceFormData {
    contract_id: string;
    vehicle_id: string;
    driver_id: string;
    service_date: string;
    origin_municipality_id: string;
    origin_address: string;
    destination_municipality_id: string;
    destination_address: string;
    planned_start_time: string;
    planned_duration: string;
    actual_start_time: string;
    actual_end_time: string;
    unit_value: string;
    quantity: string;
    billing_group: string;
    payment_method: string;
    service_status: string;
    justification: string;
    manual_entry_justification: string;
}

function thirdPartyLabel(tp: ThirdPartyOption): string {
    if (tp.is_natural_person) {
        return `${tp.first_name} ${tp.first_lastname} (${tp.identification_number})`;
    }
    return `${tp.company_name} (${tp.identification_number})`;
}

function computeActualDuration(start: string, end: string): string | null {
    if (!start || !end) return null;
    const [sh, sm] = start.split(':').map(Number);
    const [eh, em] = end.split(':').map(Number);
    const diff = eh * 60 + em - (sh * 60 + sm);
    if (diff <= 0) return null;
    return `${diff} min`;
}

/**
 * Native-Intl projection of a wall-clock day + time-of-day in
 * `timezone` to a UTC instant ISO string. Returns null when inputs are
 * incomplete or unparseable.
 */
function projectToUtcIso(
    date: string,
    time: string,
    timezone: string,
): string | null {
    if (!date || !time) return null;
    const [y, mo, d] = date.split('-').map(Number);
    const [h, m] = time.split(':').map(Number);
    if (!y || !mo || !d || Number.isNaN(h) || Number.isNaN(m)) return null;

    // Compute the offset of the target wall-clock in the target TZ by
    // formatting an arbitrary UTC instant and reading back the parts.
    // This avoids pulling in date-fns-tz (per REQ AC-7).
    const utcGuess = Date.UTC(y, mo - 1, d, h, m);
    const fmt = new Intl.DateTimeFormat('en-US', {
        timeZone: timezone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
    });
    const parts = fmt.formatToParts(new Date(utcGuess));
    const map: Record<string, string> = {};
    for (const p of parts) map[p.type] = p.value;
    const projected = Date.UTC(
        Number(map.year),
        Number(map.month) - 1,
        Number(map.day),
        Number(map.hour),
        Number(map.minute),
    );
    const offset = projected - utcGuess;
    const realUtc = utcGuess - offset;
    return new Date(realUtc).toISOString();
}

/**
 * Read-only confirmation rendered next to the planned-start-time
 * picker so operators can see exactly what's about to be persisted
 * before submitting. Sourced from the contract's TZ when available,
 * else config('app.operation_tz') via Inertia shared props.
 */
function ScheduleTimezoneHint({
    date,
    time,
    timezone,
}: {
    date: string;
    time: string;
    timezone: string;
}) {
    if (!date || !time) {
        return (
            <p className="text-xs text-muted-foreground">
                Zona horaria del servicio: <strong>{timezone}</strong>.
            </p>
        );
    }
    const utcIso = projectToUtcIso(date, time, timezone);
    return (
        <p className="text-xs text-muted-foreground">
            Se guardará como{' '}
            <strong>
                {date} {time} {timezone}
            </strong>
            {utcIso ? <> → {utcIso}</> : null}.
        </p>
    );
}

interface ServiceFormProps {
    data: ServiceFormData;
    setData: <K extends keyof ServiceFormData>(
        key: K,
        value: ServiceFormData[K],
    ) => void;
    errors: Partial<Record<keyof ServiceFormData, string>>;
    vehicles: VehicleOption[];
    drivers: DriverOption[];
    contracts: ContractOption[];
    municipalities: MunicipalityOption[];
    incidentCount?: number;
    mode: 'create' | 'edit';
    dayStatus?: DayStatus | null;
    canEditExecuted?: boolean;
    isAdmin?: boolean;
}

export default function ServiceForm({
    data,
    setData,
    errors,
    vehicles,
    drivers,
    contracts,
    municipalities,
    incidentCount,
    mode,
    dayStatus,
    canEditExecuted,
    isAdmin,
}: ServiceFormProps) {
    const invalid = (field: keyof ServiceFormData) =>
        errors[field] ? true : undefined;

    const isExecutedDay = dayStatus?.status === 'executed';
    const isFullyLocked = isExecutedDay && !canEditExecuted && !isAdmin;
    const isBillingOnly = isExecutedDay && canEditExecuted && !isAdmin;
    const isAdminEdit = isExecutedDay && isAdmin;

    const billingFields = new Set([
        'billing_group',
        'unit_value',
        'quantity',
        'payment_method',
    ]);
    const isFieldDisabled = (field: string) => {
        if (isFullyLocked) return true;
        if (isBillingOnly) return !billingFields.has(field);
        return false;
    };

    const selectedVehicle = useMemo(
        () => vehicles.find((v) => v.id === Number(data.vehicle_id)) ?? null,
        [vehicles, data.vehicle_id],
    );

    const selectedDriver = useMemo(
        () => drivers.find((d) => d.id === Number(data.driver_id)) ?? null,
        [drivers, data.driver_id],
    );

    const filteredContracts = useMemo(() => {
        if (!data.service_date) return contracts;
        // Project the operator's selected wall-clock service start into
        // a UTC instant using the contract's TZ — same pattern Service
        // backend uses. Half-open interval: [start_at, end_at). Falls
        // back to the day's start in the contract's TZ when the form
        // doesn't yet have a planned_start_time.
        const time = data.planned_start_time || '00:00';
        return contracts.filter((c) => {
            const instantIso = projectToUtcIso(
                data.service_date,
                time,
                c.timezone,
            );
            if (!instantIso) return true;
            return c.start_at <= instantIso && c.end_at > instantIso;
        });
    }, [contracts, data.service_date, data.planned_start_time]);

    const driverMissingSocialSecurity =
        selectedDriver &&
        (selectedDriver.eps_id === null ||
            selectedDriver.pension_fund_id === null);

    const actualDuration = computeActualDuration(
        data.actual_start_time,
        data.actual_end_time,
    );

    const isClosed = data.service_status === 'closed';

    // REQ-011 billing-unit semantics. Look up the selected contract's
    // billing_unit_type and derive the Cantidad label + hint so the
    // operator knows whether they're entering trips, passengers, days,
    // or hours. Legacy contracts with a null billing_unit_type fall back
    // to the generic "Cantidad (unidades del contrato)" label.
    const selectedContract = contracts.find(
        (c) => String(c.id) === String(data.contract_id),
    );
    const billingUnitLabel = (() => {
        switch (selectedContract?.billing_unit_type) {
            case 'viaje':
                return 'Cantidad (viajes)';
            case 'pasajero':
                return 'Cantidad (pasajeros)';
            case 'dia':
                return 'Cantidad (días)';
            case 'hora':
                return 'Cantidad (horas)';
            default:
                return 'Cantidad (unidades del contrato)';
        }
    })();
    const billingUnitHint = selectedContract
        ? selectedContract.billing_unit_type
            ? `Contrato ${selectedContract.contract_number} factura por ${selectedContract.billing_unit_type}.`
            : `Contrato ${selectedContract.contract_number} no tiene unidad de facturación definida.`
        : 'Seleccione un contrato para conocer la unidad de cobro.';

    // Resolve the service's IANA timezone for the schedule confirmation
    // hint near the time picker. Source priority matches the backend's
    // ServiceStoreRequest::resolveServiceTimezone — selected contract
    // first (when contract carries a TZ column), else the
    // operator-default operation_tz from the Inertia shared props.
    const sharedConfig = usePage().props.config as
        | { operation_tz?: string }
        | undefined;
    const operationTz = sharedConfig?.operation_tz ?? 'America/Bogota';
    const contractTimezone = selectedContract?.timezone ?? null;
    const resolvedTimezone = contractTimezone ?? operationTz;

    // REQ-009 retroactive-entry gate. Backend rejects service_date >=
    // today + closed; allows service_date < today + closed but only
    // with a justification. Surface the textarea when the operator
    // picks that specific combo so a clean save is possible.
    const todayIso = new Date().toISOString().slice(0, 10);
    const isPastDate =
        mode === 'create' &&
        data.service_date !== '' &&
        data.service_date < todayIso;
    const isFutureOrToday =
        mode === 'create' &&
        data.service_date !== '' &&
        data.service_date >= todayIso;
    const requiresRetroactiveJustification =
        mode === 'create' && isPastDate && isClosed;
    const illegalCreateAsClosed =
        mode === 'create' && isFutureOrToday && isClosed;

    return (
        <>
            {isFullyLocked && (
                <Alert variant="destructive">
                    <Lock className="size-4" />
                    <AlertTitle>Día ejecutado</AlertTitle>
                    <AlertDescription>
                        Este día está ejecutado. No se pueden modificar los
                        servicios.
                    </AlertDescription>
                </Alert>
            )}

            {isBillingOnly && (
                <Alert>
                    <Info className="size-4" />
                    <AlertTitle>Día ejecutado</AlertTitle>
                    <AlertDescription>
                        Día ejecutado. Solo puede modificar los campos de
                        facturación.
                    </AlertDescription>
                </Alert>
            )}

            {isAdminEdit && (
                <Alert>
                    <ShieldAlert className="size-4" />
                    <AlertTitle>Día ejecutado</AlertTitle>
                    <AlertDescription>
                        Está editando un servicio en un día ejecutado. Se
                        requiere justificación.
                    </AlertDescription>
                </Alert>
            )}

            {illegalCreateAsClosed && (
                <Alert variant="destructive">
                    <AlertTriangle className="size-4" />
                    <AlertTitle>
                        No se permite crear un servicio Cerrado para hoy o una
                        fecha futura
                    </AlertTitle>
                    <AlertDescription>
                        Cambie el estado a Abierto. Una vez ejecutado, el
                        conductor cerrará el servicio desde su portal.
                    </AlertDescription>
                </Alert>
            )}

            {requiresRetroactiveJustification && (
                <Alert>
                    <Info className="size-4" />
                    <AlertTitle>Registro retroactivo</AlertTitle>
                    <AlertDescription>
                        Está registrando un servicio cerrado de una fecha
                        anterior. Indique por qué se registra manualmente fuera
                        del flujo del conductor — quedará en la auditoría.
                    </AlertDescription>
                </Alert>
            )}

            {/* Datos del Servicio */}
            <div className="space-y-1">
                <div className="flex items-center gap-2">
                    <h3 className="text-lg font-semibold">
                        Datos del Servicio
                    </h3>
                    {mode === 'edit' &&
                        incidentCount !== undefined &&
                        incidentCount > 0 && (
                            <Badge variant="destructive">
                                {incidentCount} novedad
                                {incidentCount > 1 ? 'es' : ''}
                            </Badge>
                        )}
                </div>
            </div>

            <div className="grid gap-4 md:grid-cols-3 md:grid-rows-[auto_1fr_auto]">
                <div
                    className="group/field grid gap-2 md:row-span-3 md:grid-rows-subgrid"
                    data-error={invalid('service_date')}
                >
                    <Label htmlFor="service_date">Fecha del Servicio *</Label>
                    <Input
                        id="service_date"
                        type="date"
                        value={data.service_date}
                        aria-invalid={invalid('service_date')}
                        disabled={isFieldDisabled('service_date')}
                        onChange={(e) =>
                            setData('service_date', e.target.value)
                        }
                    />
                    <InputError message={errors.service_date} />
                </div>
                <div
                    className="group/field grid gap-2 md:row-span-3 md:grid-rows-subgrid"
                    data-error={invalid('contract_id')}
                >
                    <Label htmlFor="contract_id">Contrato *</Label>
                    <Select
                        value={data.contract_id}
                        onValueChange={(value) => setData('contract_id', value)}
                        disabled={isFieldDisabled('contract_id')}
                    >
                        <SelectTrigger
                            id="contract_id"
                            aria-invalid={invalid('contract_id')}
                        >
                            <SelectValue placeholder="Seleccionar contrato..." />
                        </SelectTrigger>
                        <SelectContent>
                            {filteredContracts.map((c) => (
                                <SelectItem key={c.id} value={String(c.id)}>
                                    {c.contract_number}
                                    {c.third_party
                                        ? ` - ${c.third_party.company_name || `${c.third_party.first_name} ${c.third_party.first_lastname}`}`
                                        : ''}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.contract_id} />
                </div>
                <div
                    className="group/field grid gap-2 md:row-span-3 md:grid-rows-subgrid"
                    data-error={invalid('service_status')}
                >
                    <Label htmlFor="service_status">Estado *</Label>
                    <Select
                        value={data.service_status}
                        onValueChange={(value) =>
                            setData('service_status', value)
                        }
                        disabled={isFieldDisabled('service_status')}
                    >
                        <SelectTrigger
                            id="service_status"
                            aria-invalid={invalid('service_status')}
                        >
                            <SelectValue placeholder="Seleccionar..." />
                        </SelectTrigger>
                        <SelectContent>
                            {Object.entries(ServiceStatus).map(
                                ([key, value]) => (
                                    <SelectItem key={key} value={value}>
                                        {ServiceStatusLabel[value]}
                                    </SelectItem>
                                ),
                            )}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.service_status} />
                </div>
            </div>

            <div className="grid gap-4 md:grid-cols-3 md:grid-rows-[auto_1fr_auto]">
                <div
                    className="group/field grid gap-2 md:row-span-3 md:grid-rows-subgrid"
                    data-error={invalid('vehicle_id')}
                >
                    <Label htmlFor="vehicle_id">Vehículo *</Label>
                    <Select
                        value={data.vehicle_id}
                        onValueChange={(value) => {
                            setData('vehicle_id', value);
                            const v = vehicles.find(
                                (v) => v.id === Number(value),
                            );
                            if (v?.is_third_party) {
                                setData('driver_id', '');
                            }
                        }}
                        disabled={isFieldDisabled('vehicle_id')}
                    >
                        <SelectTrigger
                            id="vehicle_id"
                            aria-invalid={invalid('vehicle_id')}
                        >
                            <SelectValue placeholder="Seleccionar vehículo..." />
                        </SelectTrigger>
                        <SelectContent>
                            {vehicles.map((v) => (
                                <SelectItem key={v.id} value={String(v.id)}>
                                    {v.plate}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.vehicle_id} />
                </div>

                {selectedVehicle?.is_third_party ? (
                    <div className="group/field grid gap-2 md:col-span-2 md:row-span-3 md:grid-rows-subgrid">
                        <Label>Proveedor (Tercero)</Label>
                        <div className="flex items-center rounded-md border bg-muted/50 px-3 py-2 text-sm">
                            {selectedVehicle.third_party
                                ? thirdPartyLabel(selectedVehicle.third_party)
                                : 'Sin tercero asociado'}
                        </div>
                        <div />
                    </div>
                ) : (
                    <div
                        className="group/field grid gap-2 md:col-span-2 md:row-span-3 md:grid-rows-subgrid"
                        data-error={invalid('driver_id')}
                    >
                        <div className="flex items-center gap-2">
                            <Label htmlFor="driver_id">Conductor</Label>
                            {driverMissingSocialSecurity && (
                                <TooltipProvider>
                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <AlertTriangle className="size-4 text-amber-500" />
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            El conductor no tiene seguridad
                                            social completa
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                            )}
                        </div>
                        <Select
                            value={data.driver_id}
                            onValueChange={(value) =>
                                setData('driver_id', value)
                            }
                            disabled={isFieldDisabled('driver_id')}
                        >
                            <SelectTrigger
                                id="driver_id"
                                aria-invalid={invalid('driver_id')}
                            >
                                <SelectValue placeholder="Seleccionar conductor..." />
                            </SelectTrigger>
                            <SelectContent>
                                {drivers.map((d) => (
                                    <SelectItem key={d.id} value={String(d.id)}>
                                        {d.first_name} {d.first_lastname} (
                                        {d.identification_number})
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.driver_id} />
                    </div>
                )}
            </div>

            {/* Origen y Destino */}
            <h3 className="text-lg font-semibold">Origen y Destino</h3>

            <div className="grid gap-4 md:grid-cols-2 md:grid-rows-[auto_1fr_auto]">
                <div
                    className="group/field grid gap-2 md:row-span-3 md:grid-rows-subgrid"
                    data-error={invalid('origin_municipality_id')}
                >
                    <Label htmlFor="origin_municipality_id">
                        Municipio Origen
                    </Label>
                    <MunicipalityCombobox
                        id="origin_municipality_id"
                        municipalities={municipalities}
                        value={data.origin_municipality_id}
                        onChange={(val) =>
                            setData('origin_municipality_id', val)
                        }
                        invalid={!!errors.origin_municipality_id}
                        disabled={isFieldDisabled('origin_municipality_id')}
                    />
                    <InputError message={errors.origin_municipality_id} />
                </div>
                <div
                    className="group/field grid gap-2 md:row-span-3 md:grid-rows-subgrid"
                    data-error={invalid('origin_address')}
                >
                    <Label htmlFor="origin_address">Dirección Origen</Label>
                    <Input
                        id="origin_address"
                        value={data.origin_address}
                        aria-invalid={invalid('origin_address')}
                        disabled={isFieldDisabled('origin_address')}
                        onChange={(e) =>
                            setData('origin_address', e.target.value)
                        }
                    />
                    <InputError message={errors.origin_address} />
                </div>
            </div>

            <div className="grid gap-4 md:grid-cols-2 md:grid-rows-[auto_1fr_auto]">
                <div
                    className="group/field grid gap-2 md:row-span-3 md:grid-rows-subgrid"
                    data-error={invalid('destination_municipality_id')}
                >
                    <Label htmlFor="destination_municipality_id">
                        Municipio Destino
                    </Label>
                    <MunicipalityCombobox
                        id="destination_municipality_id"
                        municipalities={municipalities}
                        value={data.destination_municipality_id}
                        onChange={(val) =>
                            setData('destination_municipality_id', val)
                        }
                        invalid={!!errors.destination_municipality_id}
                        disabled={isFieldDisabled(
                            'destination_municipality_id',
                        )}
                    />
                    <InputError message={errors.destination_municipality_id} />
                </div>
                <div
                    className="group/field grid gap-2 md:row-span-3 md:grid-rows-subgrid"
                    data-error={invalid('destination_address')}
                >
                    <Label htmlFor="destination_address">
                        Dirección Destino
                    </Label>
                    <Input
                        id="destination_address"
                        value={data.destination_address}
                        aria-invalid={invalid('destination_address')}
                        disabled={isFieldDisabled('destination_address')}
                        onChange={(e) =>
                            setData('destination_address', e.target.value)
                        }
                    />
                    <InputError message={errors.destination_address} />
                </div>
            </div>

            {/* Horarios */}
            <h3 className="text-lg font-semibold">Horarios</h3>

            <div className="grid gap-4 md:grid-cols-2 md:grid-rows-[auto_1fr_auto]">
                <div
                    className="group/field grid gap-2 md:row-span-3 md:grid-rows-subgrid"
                    data-error={invalid('planned_start_time')}
                >
                    <Label htmlFor="planned_start_time">
                        Hora Inicio Planificada *
                    </Label>
                    <Input
                        id="planned_start_time"
                        type="time"
                        value={data.planned_start_time}
                        aria-invalid={invalid('planned_start_time')}
                        disabled={isFieldDisabled('planned_start_time')}
                        onChange={(e) =>
                            setData('planned_start_time', e.target.value)
                        }
                    />
                    <InputError message={errors.planned_start_time} />
                    <ScheduleTimezoneHint
                        date={data.service_date}
                        time={data.planned_start_time}
                        timezone={resolvedTimezone}
                    />
                </div>
                <div
                    className="group/field grid gap-2 md:row-span-3 md:grid-rows-subgrid"
                    data-error={invalid('planned_duration')}
                >
                    <Label htmlFor="planned_duration">
                        Duración Planificada (min) *
                    </Label>
                    <Input
                        id="planned_duration"
                        type="number"
                        value={data.planned_duration}
                        aria-invalid={invalid('planned_duration')}
                        disabled={isFieldDisabled('planned_duration')}
                        onChange={(e) =>
                            setData('planned_duration', e.target.value)
                        }
                    />
                    <InputError message={errors.planned_duration} />
                </div>
            </div>

            <div className="grid gap-4 md:grid-cols-3 md:grid-rows-[auto_1fr_auto]">
                <div
                    className="group/field grid gap-2 md:row-span-3 md:grid-rows-subgrid"
                    data-error={invalid('actual_start_time')}
                >
                    <Label htmlFor="actual_start_time">
                        Hora Inicio Real{isClosed && ' *'}
                    </Label>
                    <Input
                        id="actual_start_time"
                        type="time"
                        value={data.actual_start_time}
                        aria-invalid={invalid('actual_start_time')}
                        disabled={isFieldDisabled('actual_start_time')}
                        onChange={(e) =>
                            setData('actual_start_time', e.target.value)
                        }
                    />
                    <InputError message={errors.actual_start_time} />
                </div>
                <div
                    className="group/field grid gap-2 md:row-span-3 md:grid-rows-subgrid"
                    data-error={invalid('actual_end_time')}
                >
                    <Label htmlFor="actual_end_time">
                        Hora Fin Real{isClosed && ' *'}
                    </Label>
                    <Input
                        id="actual_end_time"
                        type="time"
                        value={data.actual_end_time}
                        aria-invalid={invalid('actual_end_time')}
                        disabled={isFieldDisabled('actual_end_time')}
                        onChange={(e) =>
                            setData('actual_end_time', e.target.value)
                        }
                    />
                    <InputError message={errors.actual_end_time} />
                </div>
                <div className="group/field grid gap-2 md:row-span-3 md:grid-rows-subgrid">
                    <Label>Duración Real</Label>
                    <div className="flex items-center rounded-md border bg-muted/50 px-3 py-2 text-sm">
                        {actualDuration ?? '—'}
                    </div>
                    <div />
                </div>
            </div>

            {/* Facturación */}
            <h3 className="text-lg font-semibold">Facturación</h3>

            <div className="grid gap-4 md:grid-cols-4 md:grid-rows-[auto_1fr_auto]">
                <div
                    className="group/field grid gap-2 md:row-span-3 md:grid-rows-subgrid"
                    data-error={invalid('billing_group')}
                >
                    <Label htmlFor="billing_group">Grupo de Facturación</Label>
                    <Input
                        id="billing_group"
                        value={data.billing_group}
                        aria-invalid={invalid('billing_group')}
                        disabled={isFieldDisabled('billing_group')}
                        onChange={(e) =>
                            setData('billing_group', e.target.value)
                        }
                    />
                    <InputError message={errors.billing_group} />
                </div>
                <div
                    className="group/field grid gap-2 md:row-span-3 md:grid-rows-subgrid"
                    data-error={invalid('unit_value')}
                >
                    <Label htmlFor="unit_value">Valor Unitario (COP) *</Label>
                    <Input
                        id="unit_value"
                        type="number"
                        step="0.01"
                        value={data.unit_value}
                        aria-invalid={invalid('unit_value')}
                        disabled={isFieldDisabled('unit_value')}
                        onChange={(e) => setData('unit_value', e.target.value)}
                    />
                    <InputError message={errors.unit_value} />
                </div>
                <div
                    className="group/field grid gap-2 md:row-span-3 md:grid-rows-subgrid"
                    data-error={invalid('quantity')}
                >
                    <Label htmlFor="quantity">{billingUnitLabel} *</Label>
                    <Input
                        id="quantity"
                        type="number"
                        value={data.quantity}
                        aria-invalid={invalid('quantity')}
                        disabled={isFieldDisabled('quantity')}
                        onChange={(e) => setData('quantity', e.target.value)}
                    />
                    <p className="text-xs text-muted-foreground">
                        {billingUnitHint}
                    </p>
                    <InputError message={errors.quantity} />
                </div>
                <div
                    className="group/field grid gap-2 md:row-span-3 md:grid-rows-subgrid"
                    data-error={invalid('payment_method')}
                >
                    <Label htmlFor="payment_method">Método de Pago *</Label>
                    <Select
                        value={data.payment_method}
                        onValueChange={(value) =>
                            setData('payment_method', value)
                        }
                        disabled={isFieldDisabled('payment_method')}
                    >
                        <SelectTrigger
                            id="payment_method"
                            aria-invalid={invalid('payment_method')}
                        >
                            <SelectValue placeholder="Seleccionar..." />
                        </SelectTrigger>
                        <SelectContent>
                            {Object.entries(PaymentMethod).map(
                                ([key, value]) => (
                                    <SelectItem key={key} value={value}>
                                        {PaymentMethodLabel[value]}
                                    </SelectItem>
                                ),
                            )}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.payment_method} />
                </div>
            </div>

            {requiresRetroactiveJustification && (
                <div
                    className="group/field grid gap-2"
                    data-error={invalid('manual_entry_justification')}
                >
                    <Label htmlFor="manual_entry_justification">
                        Justificación de registro retroactivo *
                    </Label>
                    <textarea
                        id="manual_entry_justification"
                        className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        value={data.manual_entry_justification}
                        placeholder="Ej: El servicio se ejecutó sin acceso al sistema; registro histórico."
                        minLength={10}
                        maxLength={500}
                        aria-invalid={invalid('manual_entry_justification')}
                        onChange={(e) =>
                            setData(
                                'manual_entry_justification',
                                e.target.value,
                            )
                        }
                    />
                    <InputError message={errors.manual_entry_justification} />
                </div>
            )}

            {isAdminEdit && (
                <>
                    <Alert variant="destructive">
                        <AlertTriangle className="h-4 w-4" />
                        <AlertTitle>Día ejecutado</AlertTitle>
                        <AlertDescription>
                            Este servicio pertenece a un día ejecutado. La
                            modificación requiere justificación obligatoria y
                            quedará registrada en la auditoría.
                        </AlertDescription>
                    </Alert>
                    <h3 className="text-lg font-semibold">
                        Justificación del cambio
                    </h3>
                    <div
                        className="group/field grid gap-2"
                        data-error={invalid('justification')}
                    >
                        <Label htmlFor="justification">
                            Justificación del cambio *
                        </Label>
                        <textarea
                            id="justification"
                            className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                            value={data.justification}
                            placeholder="Explique el motivo de la modificación..."
                            onChange={(e) =>
                                setData('justification', e.target.value)
                            }
                        />
                        <InputError message={errors.justification} />
                    </div>
                </>
            )}
        </>
    );
}
