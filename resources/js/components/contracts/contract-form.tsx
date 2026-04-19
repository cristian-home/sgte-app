import InputError from '@/components/input-error';
import ThirdPartyCombobox, {
    type ThirdPartyOption,
} from '@/components/third-parties/third-party-combobox';
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
import { BillingUnitType } from '@/enums/BillingUnitType';

export interface ContractFormData {
    contract_number: string;
    third_party_id: string;
    contract_object: string;
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
}

function RequiredMarker() {
    return <span className="text-destructive"> *</span>;
}

export const CONTRACT_OBJECT_OPTIONS: Array<{
    value: string;
    label: string;
}> = [
    { value: 'business', label: 'Empresarial' },
    { value: 'tourism', label: 'Turismo' },
    { value: 'health', label: 'Salud' },
    { value: 'occasional', label: 'Ocasional' },
];

export const BILLING_UNIT_TYPE_OPTIONS: Array<{
    value: string;
    label: string;
}> = [
    { value: BillingUnitType.Viaje, label: 'Viaje' },
    { value: BillingUnitType.Pasajero, label: 'Pasajero' },
    { value: BillingUnitType.Dia, label: 'Día' },
    { value: BillingUnitType.Hora, label: 'Hora' },
];

export default function ContractForm({
    data,
    setData,
    errors,
    thirdParties,
    forceIncludeCustomer,
    idPrefix = '',
}: ContractFormProps) {
    const id = (name: string) => (idPrefix ? `${idPrefix}_${name}` : name);
    const invalid = (field: keyof ContractFormData) =>
        errors[field] ? true : undefined;

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
                    <ThirdPartyCombobox
                        id={id('third_party_id')}
                        thirdParties={thirdParties}
                        role="customer"
                        forceInclude={forceIncludeCustomer}
                        value={data.third_party_id || null}
                        onChange={(value) => setData('third_party_id', value)}
                        invalid={invalid('third_party_id')}
                        placeholder="Selecciona un cliente"
                    />
                    <InputError message={errors.third_party_id} />
                </div>
            </div>

            <div className="grid gap-4 md:grid-cols-3">
                <div className="grid gap-2">
                    <Label htmlFor={id('contract_object')}>
                        Objeto del Contrato
                        <RequiredMarker />
                    </Label>
                    <Select
                        value={data.contract_object}
                        onValueChange={(value) =>
                            setData('contract_object', value)
                        }
                    >
                        <SelectTrigger
                            id={id('contract_object')}
                            aria-invalid={invalid('contract_object')}
                        >
                            <SelectValue placeholder="Selecciona un objeto" />
                        </SelectTrigger>
                        <SelectContent>
                            {CONTRACT_OBJECT_OPTIONS.map((opt) => (
                                <SelectItem key={opt.value} value={opt.value}>
                                    {opt.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.contract_object} />
                </div>

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

            <div className="grid gap-2 md:max-w-xs">
                <Label htmlFor={id('billing_unit_type')}>
                    Unidad de Facturación
                </Label>
                <Select
                    value={data.billing_unit_type}
                    onValueChange={(value) =>
                        setData('billing_unit_type', value)
                    }
                >
                    <SelectTrigger
                        id={id('billing_unit_type')}
                        aria-invalid={invalid('billing_unit_type')}
                    >
                        <SelectValue placeholder="Selecciona una unidad (opcional)" />
                    </SelectTrigger>
                    <SelectContent>
                        {BILLING_UNIT_TYPE_OPTIONS.map((opt) => (
                            <SelectItem key={opt.value} value={opt.value}>
                                {opt.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
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
