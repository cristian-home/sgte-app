import InputError from '@/components/input-error';
import ServiceCombobox, {
    type ServiceOption,
} from '@/components/services/service-combobox';
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
import { Switch } from '@/components/ui/switch';
import { dateFormatter, parseDueDate } from '@/lib/document-status';

import type { IncidentType } from '@/types/models';

export type IncidentTypeOption = Pick<
    IncidentType,
    'id' | 'code' | 'name' | 'severity' | 'affects_billing_default'
>;

export interface ServiceIncidentFormData {
    service_id: string;
    incident_type_id: string;
    description: string;
    affects_billing: boolean;
    additional_value: string;
}

export interface PreselectedService {
    id: number;
    service_date: string | null;
    vehicle?: { id: number; plate: string } | null;
    contract?: {
        id: number;
        contract_number: string;
        third_party?: {
            id: number;
            is_natural_person: boolean;
            first_name: string | null;
            first_lastname: string | null;
            company_name: string | null;
        } | null;
    } | null;
    driver?: { id: number; first_name: string; first_lastname: string } | null;
}

interface ServiceIncidentFormProps {
    data: ServiceIncidentFormData;
    setData: <K extends keyof ServiceIncidentFormData>(
        key: K,
        value: ServiceIncidentFormData[K],
    ) => void;
    setDataBulk?: (next: ServiceIncidentFormData) => void;
    errors: Partial<Record<keyof ServiceIncidentFormData, string>>;
    incidentTypes: IncidentTypeOption[];
    /**
     * Option list for the service picker. Pass null when the form is
     * anchored to a preselected service (driver-portal, services/show).
     */
    services?: ServiceOption[] | null;
    /**
     * When set, the service picker is hidden and the read-only summary
     * block renders instead. Incoming from the `?service_id=X` path.
     */
    preselectedService?: PreselectedService | null;
    idPrefix?: string;
}

function RequiredMarker() {
    return <span className="text-destructive"> *</span>;
}

function formatDate(date: string | null): string {
    const parsed = parseDueDate(date);
    if (parsed === null) {
        return '—';
    }
    return dateFormatter.format(parsed);
}

function customerName(
    tp: NonNullable<PreselectedService['contract']>['third_party'] | null,
): string {
    if (!tp) return '—';
    if (tp.is_natural_person) {
        return (
            [tp.first_name, tp.first_lastname]
                .filter(Boolean)
                .join(' ')
                .trim() || '—'
        );
    }
    return tp.company_name ?? '—';
}

function driverName(driver: PreselectedService['driver']): string {
    if (!driver) return '—';
    return [driver.first_name, driver.first_lastname]
        .filter(Boolean)
        .join(' ')
        .trim();
}

export default function ServiceIncidentForm({
    data,
    setData,
    errors,
    incidentTypes,
    services,
    preselectedService,
    idPrefix = '',
}: ServiceIncidentFormProps) {
    const id = (name: string) => (idPrefix ? `${idPrefix}_${name}` : name);
    const invalid = (field: keyof ServiceIncidentFormData) =>
        errors[field] ? true : undefined;

    function handleIncidentTypeChange(value: string) {
        const type = incidentTypes.find((t) => String(t.id) === value);
        setData('incident_type_id', value);
        if (type) {
            setData('affects_billing', type.affects_billing_default ?? false);
        }
    }

    return (
        <div className="space-y-6">
            {/* Service block — either preselected summary OR combobox */}
            {preselectedService ? (
                <div className="space-y-2 rounded-md border bg-muted/30 p-4">
                    <Label className="text-xs text-muted-foreground uppercase">
                        Servicio
                    </Label>
                    <div className="grid gap-2 text-sm md:grid-cols-4">
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Fecha
                            </p>
                            <p className="font-medium">
                                {formatDate(preselectedService.service_date)}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Vehículo
                            </p>
                            <p className="font-mono">
                                {preselectedService.vehicle?.plate ?? '—'}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Contrato
                            </p>
                            <p className="font-mono">
                                {preselectedService.contract?.contract_number ??
                                    '—'}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Cliente
                            </p>
                            <p>
                                {customerName(
                                    preselectedService.contract?.third_party ??
                                        null,
                                )}
                            </p>
                        </div>
                    </div>
                    {preselectedService.driver && (
                        <p className="text-xs text-muted-foreground">
                            Conductor:{' '}
                            <span className="font-medium text-foreground">
                                {driverName(preselectedService.driver)}
                            </span>
                        </p>
                    )}
                    <p className="text-xs text-muted-foreground italic">
                        Preseleccionado desde el servicio.
                    </p>
                </div>
            ) : (
                <div className="grid gap-2">
                    <Label htmlFor={id('service_id')}>
                        Servicio
                        <RequiredMarker />
                    </Label>
                    <ServiceCombobox
                        id={id('service_id')}
                        services={services ?? []}
                        value={data.service_id || null}
                        onChange={(value) => setData('service_id', value)}
                        invalid={invalid('service_id')}
                    />
                    <InputError message={errors.service_id} />
                </div>
            )}

            <div className="grid gap-4 md:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor={id('incident_type_id')}>
                        Tipo de Novedad
                        <RequiredMarker />
                    </Label>
                    <Select
                        value={data.incident_type_id}
                        onValueChange={handleIncidentTypeChange}
                    >
                        <SelectTrigger
                            id={id('incident_type_id')}
                            aria-invalid={invalid('incident_type_id')}
                        >
                            <SelectValue placeholder="Selecciona un tipo" />
                        </SelectTrigger>
                        <SelectContent>
                            {incidentTypes.map((type) => (
                                <SelectItem
                                    key={type.id}
                                    value={String(type.id)}
                                >
                                    <span className="flex items-center gap-2">
                                        {type.name}
                                        <Badge
                                            variant={
                                                type.severity === 'major'
                                                    ? 'destructive'
                                                    : type.severity === 'minor'
                                                      ? 'secondary'
                                                      : 'outline'
                                            }
                                            className="text-xs"
                                        >
                                            {type.severity === 'major'
                                                ? 'Mayor'
                                                : type.severity === 'minor'
                                                  ? 'Menor'
                                                  : 'Informativo'}
                                        </Badge>
                                    </span>
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.incident_type_id} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor={id('additional_value')}>
                        Valor Adicional
                    </Label>
                    <div className="relative">
                        <span className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-muted-foreground">
                            $
                        </span>
                        <Input
                            id={id('additional_value')}
                            type="number"
                            step="0.01"
                            min="0"
                            value={data.additional_value}
                            aria-invalid={invalid('additional_value')}
                            onChange={(e) =>
                                setData('additional_value', e.target.value)
                            }
                            className="pl-7 tabular-nums"
                        />
                    </div>
                    <InputError message={errors.additional_value} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor={id('description')}>
                    Descripción
                    <RequiredMarker />
                </Label>
                <textarea
                    id={id('description')}
                    value={data.description}
                    rows={5}
                    aria-invalid={invalid('description')}
                    onChange={(e) => setData('description', e.target.value)}
                    className="flex min-h-24 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive"
                />
                <InputError message={errors.description} />
            </div>

            <div className="flex items-center gap-3">
                <Switch
                    id={id('affects_billing')}
                    checked={data.affects_billing}
                    onCheckedChange={(checked) =>
                        setData('affects_billing', checked)
                    }
                />
                <Label htmlFor={id('affects_billing')}>
                    Afecta facturación
                </Label>
            </div>
            <InputError message={errors.affects_billing} />
        </div>
    );
}
