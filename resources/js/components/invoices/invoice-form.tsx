import { Loader2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import ServicePickerTable, {
    rowBillableTotal,
    type ServicePickerRow,
} from '@/components/invoices/service-picker-table';
import ThirdPartyCombobox, {
    type ThirdPartyOption,
} from '@/components/third-parties/third-party-combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import MoneyInput from '@/components/ui/money-input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export interface InvoiceFormData {
    third_party_id: string;
    invoice_number: string;
    total_value: string;
    issue_date: string;
    payment_status: string;
    notes: string;
    service_ids: number[];
    override_justification: string;
}

export interface EligibleServicesPayload {
    cleanCandidates: ServicePickerRow[];
    blockedCandidates: ServicePickerRow[];
}

interface InvoiceFormProps {
    data: InvoiceFormData;
    setData: <K extends keyof InvoiceFormData>(
        key: K,
        value: InvoiceFormData[K],
    ) => void;
    errors: Partial<Record<keyof InvoiceFormData | 'service_ids', string>>;
    thirdParties: ThirdPartyOption[];
    /**
     * Extra customers that MUST appear in the combobox even if they
     * are no longer flagged `is_customer = true`. Used by the edit
     * form so an invoice's current customer never disappears.
     */
    forceIncludeCustomer?: ThirdPartyOption[];
    idPrefix?: string;
    /**
     * When true, the total_value Input is rendered read-only with a
     * muted "(calculado automáticamente)" note. Driven by the parent
     * from invoice.services_count > 0.
     */
    isTotalLocked?: boolean;
    /**
     * Services-count used in the locked-state note. Only rendered
     * when isTotalLocked is true.
     */
    servicesCount?: number;
    /** Create mode → show the inline picker; edit mode → hide it. */
    mode?: 'create' | 'edit';
    /** Eligible services payload (null until the parent loads it). */
    eligibleServices?: EligibleServicesPayload | null;
    /** Spinner state while the parent is fetching eligible services. */
    loadingEligible?: boolean;
    /** Called when the customer combobox changes (create mode only). */
    onThirdPartyChange?: (id: string) => void;
}

function RequiredMarker() {
    return <span className="text-destructive"> *</span>;
}

export const PAYMENT_STATUS_OPTIONS: Array<{
    value: string;
    label: string;
}> = [
    { value: 'pending', label: 'Pendiente' },
    { value: 'paid', label: 'Pagado' },
    { value: 'overdue', label: 'Vencido' },
];

export default function InvoiceForm({
    data,
    setData,
    errors,
    thirdParties,
    forceIncludeCustomer,
    idPrefix = '',
    isTotalLocked = false,
    servicesCount = 0,
    mode = 'edit',
    eligibleServices = null,
    loadingEligible = false,
    onThirdPartyChange,
}: InvoiceFormProps) {
    const id = (name: string) => (idPrefix ? `${idPrefix}_${name}` : name);
    const invalid = (field: keyof InvoiceFormData) =>
        errors[field] ? true : undefined;

    const [pickerSearch, setPickerSearch] = useState('');
    const [showBlocked, setShowBlocked] = useState(false);

    const showPicker =
        mode === 'create' && Boolean(data.third_party_id) && !isTotalLocked;

    function handleThirdPartyChange(value: string) {
        setData('third_party_id', value);
        setData('service_ids', []);
        setData('override_justification', '');
        setShowBlocked(false);
        setPickerSearch('');
        onThirdPartyChange?.(value);
    }

    // Auto-fill total_value from the selected services' billable amount.
    // The input stays editable (no readOnly) — user can override the
    // suggestion, but the server reconciles after attach so the final
    // persisted value still reflects the actual services.
    const selectedTotal = useMemo(() => {
        if (!showPicker || !eligibleServices) return 0;
        const idSet = new Set(data.service_ids);
        const rows: ServicePickerRow[] = [
            ...eligibleServices.cleanCandidates,
            ...eligibleServices.blockedCandidates,
        ];
        return rows
            .filter((row) => idSet.has(row.id))
            .reduce((acc, row) => acc + rowBillableTotal(row), 0);
    }, [showPicker, eligibleServices, data.service_ids]);

    useEffect(() => {
        if (!showPicker) return;
        if (data.service_ids.length === 0) return;
        const next = String(selectedTotal);
        if (next !== data.total_value) {
            setData('total_value', next);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [selectedTotal, showPicker, data.service_ids.length]);

    return (
        <div className="space-y-6">
            <div className="grid gap-4 md:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor={id('invoice_number')}>
                        Número de Factura
                        <RequiredMarker />
                    </Label>
                    <Input
                        id={id('invoice_number')}
                        value={data.invoice_number}
                        aria-invalid={invalid('invoice_number')}
                        onChange={(e) =>
                            setData('invoice_number', e.target.value)
                        }
                    />
                    <InputError message={errors.invoice_number} />
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
                        onChange={handleThirdPartyChange}
                        invalid={invalid('third_party_id')}
                        placeholder="Selecciona un cliente"
                    />
                    <InputError message={errors.third_party_id} />
                </div>
            </div>

            {showPicker && (
                <div className="space-y-2">
                    <Label>Servicios a facturar</Label>
                    {loadingEligible ? (
                        <div className="flex items-center gap-2 rounded-md border border-dashed bg-muted/30 p-6 text-sm text-muted-foreground">
                            <Loader2 className="size-4 animate-spin" />
                            Cargando servicios elegibles…
                        </div>
                    ) : eligibleServices ? (
                        eligibleServices.cleanCandidates.length === 0 &&
                        eligibleServices.blockedCandidates.length === 0 ? (
                            <div className="rounded-md border border-dashed bg-muted/30 p-6 text-sm text-muted-foreground">
                                No hay servicios elegibles para este cliente.
                                Puedes crear la factura igual y asociar
                                servicios después desde su detalle.
                            </div>
                        ) : (
                            <ServicePickerTable
                                candidates={eligibleServices.cleanCandidates}
                                blockedCandidates={
                                    eligibleServices.blockedCandidates
                                }
                                selectedIds={data.service_ids}
                                onSelectedIdsChange={(ids) =>
                                    setData('service_ids', ids)
                                }
                                showBlocked={showBlocked}
                                onShowBlockedChange={setShowBlocked}
                                justification={data.override_justification}
                                onJustificationChange={(v) =>
                                    setData('override_justification', v)
                                }
                                justificationError={
                                    errors.override_justification
                                }
                                serviceIdsError={errors.service_ids}
                                search={pickerSearch}
                                onSearchChange={setPickerSearch}
                                idPrefix={id('picker')}
                            />
                        )
                    ) : null}
                </div>
            )}

            <div className="grid gap-4 md:grid-cols-3">
                <div className="grid gap-2">
                    <Label htmlFor={id('issue_date')}>
                        Fecha de Emisión
                        <RequiredMarker />
                    </Label>
                    <Input
                        id={id('issue_date')}
                        type="date"
                        value={data.issue_date}
                        aria-invalid={invalid('issue_date')}
                        onChange={(e) => setData('issue_date', e.target.value)}
                    />
                    <InputError message={errors.issue_date} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor={id('total_value')}>
                        Valor Total
                        <RequiredMarker />
                    </Label>
                    <MoneyInput
                        id={id('total_value')}
                        value={data.total_value}
                        onValueChange={(raw) => setData('total_value', raw)}
                        readOnly={isTotalLocked}
                        invalid={invalid('total_value')}
                        className="tabular-nums"
                    />
                    {isTotalLocked ? (
                        <p className="text-xs text-muted-foreground italic">
                            (calculado automáticamente — hay {servicesCount}{' '}
                            servicio
                            {servicesCount === 1 ? '' : 's'} asociado
                            {servicesCount === 1 ? '' : 's'})
                        </p>
                    ) : (
                        showPicker &&
                        data.service_ids.length > 0 && (
                            <p className="text-xs text-muted-foreground italic">
                                (sugerido por los servicios seleccionados —
                                puedes ajustarlo)
                            </p>
                        )
                    )}
                    <InputError message={errors.total_value} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor={id('payment_status')}>
                        Estado
                        <RequiredMarker />
                    </Label>
                    <Select
                        value={data.payment_status}
                        onValueChange={(value) =>
                            setData('payment_status', value)
                        }
                    >
                        <SelectTrigger
                            id={id('payment_status')}
                            aria-invalid={invalid('payment_status')}
                        >
                            <SelectValue placeholder="Selecciona un estado" />
                        </SelectTrigger>
                        <SelectContent>
                            {PAYMENT_STATUS_OPTIONS.map((opt) => (
                                <SelectItem key={opt.value} value={opt.value}>
                                    {opt.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.payment_status} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor={id('notes')}>Observaciones</Label>
                <textarea
                    id={id('notes')}
                    value={data.notes}
                    rows={4}
                    aria-invalid={invalid('notes')}
                    onChange={(e) => setData('notes', e.target.value)}
                    className="flex min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive"
                />
                <InputError message={errors.notes} />
            </div>
        </div>
    );
}
