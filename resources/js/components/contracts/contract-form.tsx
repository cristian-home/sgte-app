import { usePage } from '@inertiajs/react';
import {
    Briefcase,
    CalendarClock,
    CalendarDays,
    Clock,
    HeartPulse,
    Plane,
    Plus,
    Route,
    Users,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import {
    Choicebox,
    ChoiceboxIndicator,
    ChoiceboxItem,
    ChoiceboxItemHeader,
    ChoiceboxItemTitle,
} from '@/components/kibo-ui/choicebox';
import { type MunicipalityOption } from '@/components/municipality-combobox';
import ThirdPartyCombobox, {
    type ThirdPartyOption,
} from '@/components/third-parties/third-party-combobox';
import ThirdPartyDialog from '@/components/third-parties/third-party-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import TimezoneCombobox from '@/components/ui/timezone-combobox';
import { BillingUnitType } from '@/enums/BillingUnitType';
import type { DocumentTypeOption } from '@/components/third-parties/third-party-form';

export interface ContractFormData {
    contract_number: string;
    third_party_id: string;
    contract_object: string;
    timezone: string;
    start_date: string;
    end_date: string;
    route_description: string;
    is_generic: boolean;
    active: boolean;
    billing_unit_type: string;
}

interface ContractFormProps {
    data: ContractFormData;
    setData: <K extends keyof ContractFormData>(
        key: K,
        value: ContractFormData[K],
    ) => void;
    errors: Partial<Record<keyof ContractFormData, string>>;
    thirdParties: ThirdPartyOption[];
    /**
     * Extra customers that MUST appear in the combobox even if they
     * are no longer flagged `is_customer = true`. Used by the edit
     * form so a contract's current customer never disappears from
     * the option list.
     */
    forceIncludeCustomer?: ThirdPartyOption[];
    idPrefix?: string;
    /**
     * When true, render a "+" button next to the Cliente combobox that
     * launches a nested ThirdPartyCreateDialog. After successful creation
     * the new tercero is auto-selected via flash data. Requires
     * `documentTypes` + `municipalities` to be passed so the nested dialog
     * can render its form.
     */
    allowCreateThirdParty?: boolean;
    documentTypes?: DocumentTypeOption[];
    municipalities?: MunicipalityOption[];
}

function RequiredMarker() {
    return <span className="text-destructive"> *</span>;
}

export const CONTRACT_OBJECT_OPTIONS: Array<{
    value: string;
    label: string;
    icon: typeof Briefcase;
}> = [
    { value: 'business', label: 'Empresarial', icon: Briefcase },
    { value: 'tourism', label: 'Turismo', icon: Plane },
    { value: 'health', label: 'Salud', icon: HeartPulse },
    { value: 'occasional', label: 'Ocasional', icon: CalendarClock },
];

export const BILLING_UNIT_TYPE_OPTIONS: Array<{
    value: string;
    label: string;
    icon: typeof Route;
}> = [
    { value: BillingUnitType.Viaje, label: 'Viaje', icon: Route },
    { value: BillingUnitType.Pasajero, label: 'Pasajero', icon: Users },
    { value: BillingUnitType.Dia, label: 'Día', icon: CalendarDays },
    { value: BillingUnitType.Hora, label: 'Hora', icon: Clock },
];

export default function ContractForm({
    data,
    setData,
    errors,
    thirdParties,
    forceIncludeCustomer,
    idPrefix = '',
    allowCreateThirdParty = false,
    documentTypes,
    municipalities,
}: ContractFormProps) {
    const id = (name: string) => (idPrefix ? `${idPrefix}_${name}` : name);
    const invalid = (field: keyof ContractFormData) =>
        errors[field] ? true : undefined;

    const [createTpOpen, setCreateTpOpen] = useState(false);
    const page = usePage();
    const flash = page.props.flash as
        | { created_third_party_id?: number }
        | undefined;
    // Track the last id we've consumed so a stale flash on the next render
    // doesn't re-fire the auto-select.
    const consumedTpFlashRef = useRef<number | null>(null);
    useEffect(() => {
        const id = flash?.created_third_party_id;
        if (!id || consumedTpFlashRef.current === id) return;
        consumedTpFlashRef.current = id;
        setData('third_party_id', String(id));
    }, [flash?.created_third_party_id, setData]);

    const sharedConfig = usePage().props.config as
        | { operation_tz?: string }
        | undefined;
    const operationTz = sharedConfig?.operation_tz ?? 'America/Bogota';
    const timezoneLabel = data.timezone || operationTz;

    return (
        <div className="space-y-6">
            <div className="grid gap-4 md:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor={id('contract_number')}>
                        Número de Contrato
                        {!data.is_generic && <RequiredMarker />}
                    </Label>
                    {data.is_generic ? (
                        <p className="text-sm text-muted-foreground">
                            Se generará automáticamente al guardar
                            (GEN-####-YYYY).
                        </p>
                    ) : (
                        <Input
                            id={id('contract_number')}
                            value={data.contract_number}
                            aria-invalid={invalid('contract_number')}
                            onChange={(e) =>
                                setData('contract_number', e.target.value)
                            }
                        />
                    )}
                    <InputError message={errors.contract_number} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor={id('third_party_id')}>
                        Cliente
                        <RequiredMarker />
                    </Label>
                    {/* min-w-0 on the flex row itself is required because
                     * the row is a grid item (grid items default to
                     * `min-width: auto` and refuse to shrink below
                     * content). Without it, a long client label widens
                     * the trigger and pushes the "+" button into the
                     * next grid column. */}
                    <div className="flex min-w-0 gap-2">
                        <div className="min-w-0 flex-1">
                            <ThirdPartyCombobox
                                id={id('third_party_id')}
                                thirdParties={thirdParties}
                                role="customer"
                                forceInclude={forceIncludeCustomer}
                                value={data.third_party_id || null}
                                onChange={(value) =>
                                    setData('third_party_id', value)
                                }
                                invalid={invalid('third_party_id')}
                                placeholder="Selecciona un cliente"
                            />
                        </div>
                        {allowCreateThirdParty &&
                            documentTypes &&
                            municipalities && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    onClick={() => setCreateTpOpen(true)}
                                    aria-label="Crear nuevo cliente"
                                    title="Crear nuevo cliente"
                                >
                                    <Plus className="size-4" />
                                </Button>
                            )}
                    </div>
                    <InputError message={errors.third_party_id} />
                </div>
            </div>

            {allowCreateThirdParty && documentTypes && municipalities && (
                <ThirdPartyDialog
                    open={createTpOpen}
                    onOpenChange={setCreateTpOpen}
                    mode="create"
                    cascade
                    documentTypes={documentTypes}
                    municipalities={municipalities}
                />
            )}

            <div className="grid gap-2">
                <Label htmlFor={id('contract_object')}>
                    Objeto del Contrato
                    <RequiredMarker />
                </Label>
                <Choicebox
                    id={id('contract_object')}
                    value={data.contract_object}
                    onValueChange={(value) => setData('contract_object', value)}
                    aria-invalid={invalid('contract_object')}
                    className="grid grid-cols-2 gap-2 sm:grid-cols-4"
                >
                    {CONTRACT_OBJECT_OPTIONS.map((opt) => {
                        const Icon = opt.icon;
                        return (
                            <ChoiceboxItem
                                key={opt.value}
                                id={`${id('contract_object')}-${opt.value}`}
                                value={opt.value}
                            >
                                <ChoiceboxItemHeader>
                                    <ChoiceboxItemTitle className="flex items-center gap-2">
                                        <Icon aria-hidden className="size-4" />
                                        {opt.label}
                                    </ChoiceboxItemTitle>
                                </ChoiceboxItemHeader>
                                <ChoiceboxIndicator />
                            </ChoiceboxItem>
                        );
                    })}
                </Choicebox>
                <InputError message={errors.contract_object} />
            </div>

            <div className="grid gap-4 md:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor={id('start_date')}>
                        Fecha de Inicio
                        <RequiredMarker />
                    </Label>
                    <Input
                        id={id('start_date')}
                        type="date"
                        value={data.start_date}
                        aria-invalid={invalid('start_date')}
                        onChange={(e) => setData('start_date', e.target.value)}
                    />
                    <InputError message={errors.start_date} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor={id('end_date')}>
                        Fecha de Fin
                        <RequiredMarker />
                    </Label>
                    <Input
                        id={id('end_date')}
                        type="date"
                        value={data.end_date}
                        aria-invalid={invalid('end_date')}
                        onChange={(e) => setData('end_date', e.target.value)}
                    />
                    <InputError message={errors.end_date} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor={id('timezone')}>Zona horaria</Label>
                <TimezoneCombobox
                    id={id('timezone')}
                    value={data.timezone || operationTz}
                    onChange={(value) => setData('timezone', value)}
                    invalid={invalid('timezone')}
                    placeholder={operationTz}
                />
                <p className="text-xs text-muted-foreground">
                    Las fechas del contrato se almacenan como instantes en{' '}
                    <strong>{timezoneLabel}</strong>. Por defecto:{' '}
                    <code>{operationTz}</code>.
                </p>
                <InputError message={errors.timezone} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={id('route_description')}>
                    Recorrido / Ruta
                    <RequiredMarker />
                </Label>
                <textarea
                    id={id('route_description')}
                    value={data.route_description}
                    rows={4}
                    aria-invalid={invalid('route_description')}
                    onChange={(e) =>
                        setData('route_description', e.target.value)
                    }
                    className="flex min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive"
                />
                <InputError message={errors.route_description} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={id('billing_unit_type')}>
                    Unidad de Facturación
                </Label>
                <Choicebox
                    id={id('billing_unit_type')}
                    value={data.billing_unit_type}
                    onValueChange={(value) =>
                        setData('billing_unit_type', value)
                    }
                    aria-invalid={invalid('billing_unit_type')}
                    className="grid grid-cols-2 gap-2 sm:grid-cols-4"
                >
                    {BILLING_UNIT_TYPE_OPTIONS.map((opt) => {
                        const Icon = opt.icon;
                        return (
                            <ChoiceboxItem
                                key={opt.value}
                                id={`${id('billing_unit_type')}-${opt.value}`}
                                value={opt.value}
                                className="h-full items-center!"
                            >
                                <ChoiceboxItemHeader>
                                    <ChoiceboxItemTitle className="flex items-center gap-2">
                                        <Icon
                                            aria-hidden
                                            className="size-4 shrink-0"
                                        />
                                        <span>{opt.label}</span>
                                    </ChoiceboxItemTitle>
                                </ChoiceboxItemHeader>
                                <ChoiceboxIndicator />
                            </ChoiceboxItem>
                        );
                    })}
                </Choicebox>
                <p className="text-xs text-muted-foreground">
                    Define cómo se factura este contrato (viaje, pasajero, día u
                    hora). Aparece como "Cantidad (…)" en el formulario de
                    servicios.
                </p>
                <InputError message={errors.billing_unit_type} />
            </div>

            <div className="flex flex-wrap items-center gap-6">
                <div className="flex items-center gap-3">
                    <Switch
                        id={id('is_generic')}
                        checked={data.is_generic}
                        onCheckedChange={(checked) =>
                            setData('is_generic', checked)
                        }
                    />
                    <Label htmlFor={id('is_generic')}>Contrato Genérico</Label>
                </div>
                <div className="flex items-center gap-3">
                    <Switch
                        id={id('active')}
                        checked={data.active}
                        onCheckedChange={(checked) =>
                            setData('active', checked)
                        }
                    />
                    <Label htmlFor={id('active')}>Activo</Label>
                </div>
            </div>
            <InputError message={errors.is_generic} />
            <InputError message={errors.active} />
        </div>
    );
}
