import { Loader2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
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
    /** Preview del próximo número de factura (create-mode, readonly). */
    nextInvoiceNumberPreview?: string;
    /** Permite editar el número de factura en edit mode (solo super admin). */
    canEditInvoiceNumber?: boolean;
    /** Servicios ya asociados a esta factura (solo edit mode). */
    attachedServices?: ServicePickerRow[];
}

function RequiredMarker() {
    return <span className="text-destructive"> *</span>;
}

/**
 * Slot único bajo cada input que SIEMPRE reserva una línea de alto
 * (≈20px). Renderiza con prioridad: error > hint > vacío (espaciador
 * invisible). Sirve para que las celdas vecinas en la misma row de
 * grid tengan exactamente la misma altura aunque solo una tenga error
 * o texto de ayuda. Mantén copy/errores a una línea — si la validación
 * podría producir mensajes largos, mejora el `messages()` del
 * FormRequest correspondiente.
 */
function FieldFooter({
    error,
    children,
}: {
    error?: string;
    children?: React.ReactNode;
}) {
    if (error) {
        return (
            <p className="min-h-[1.25rem] text-sm text-destructive">{error}</p>
        );
    }
    return (
        <p
            className="min-h-[1.25rem] text-xs text-muted-foreground italic"
            aria-hidden={children ? undefined : true}
        >
            {children ?? ' '}
        </p>
    );
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
    nextInvoiceNumberPreview,
    canEditInvoiceNumber = false,
    attachedServices = [],
}: InvoiceFormProps) {
    const id = (name: string) => (idPrefix ? `${idPrefix}_${name}` : name);
    const invalid = (field: keyof InvoiceFormData) =>
        errors[field] ? true : undefined;

    const [pickerSearch, setPickerSearch] = useState('');
    const [showBlocked, setShowBlocked] = useState(false);

    const showPickerCreate =
        mode === 'create' && Boolean(data.third_party_id) && !isTotalLocked;
    const showPickerEdit = mode === 'edit' && Boolean(data.third_party_id);
    const showPicker = showPickerCreate || showPickerEdit;
    const isCustomerLocked = mode === 'edit' && servicesCount > 0;
    const isInvoiceNumberReadOnly =
        mode === 'create' || (mode === 'edit' && !canEditInvoiceNumber);

    function handleThirdPartyChange(value: string) {
        if (isCustomerLocked) return;
        setData('third_party_id', value);
        // En create reseteamos el set de seleccionados; en edit el
        // parent ya re-hidrata data.service_ids con los attached cuando
        // cambia el invoice, y aquí no aplicaría porque el combobox
        // está bloqueado cuando hay servicios.
        if (mode === 'create') {
            setData('service_ids', []);
        }
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
        if (!showPicker) return 0;
        const idSet = new Set(data.service_ids);
        const rows: ServicePickerRow[] = [
            ...(eligibleServices?.cleanCandidates ?? []),
            ...(eligibleServices?.blockedCandidates ?? []),
            ...attachedServices,
        ];
        return rows
            .filter((row) => idSet.has(row.id))
            .reduce((acc, row) => acc + rowBillableTotal(row), 0);
    }, [showPicker, eligibleServices, attachedServices, data.service_ids]);

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
                        value={
                            mode === 'create'
                                ? (nextInvoiceNumberPreview ?? '')
                                : data.invoice_number
                        }
                        readOnly={isInvoiceNumberReadOnly}
                        aria-invalid={invalid('invoice_number')}
                        onChange={(e) =>
                            setData('invoice_number', e.target.value)
                        }
                        className={
                            isInvoiceNumberReadOnly
                                ? 'bg-muted/40 font-mono'
                                : 'font-mono'
                        }
                    />
                    <FieldFooter error={errors.invoice_number}>
                        {mode === 'create'
                            ? 'Asignado automáticamente al guardar.'
                            : mode === 'edit' && !canEditInvoiceNumber
                              ? 'No editable una vez creada.'
                              : null}
                    </FieldFooter>
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
                        disabled={isCustomerLocked}
                    />
                    <FieldFooter error={errors.third_party_id}>
                        {isCustomerLocked
                            ? 'Bloqueado mientras haya servicios.'
                            : null}
                    </FieldFooter>
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
                        eligibleServices.blockedCandidates.length === 0 &&
                        attachedServices.length === 0 ? (
                            <div className="rounded-md border border-dashed bg-muted/30 p-6 text-sm text-muted-foreground">
                                {mode === 'create'
                                    ? 'No hay servicios elegibles para este cliente. Puedes crear la factura igual y asociar servicios después desde su detalle.'
                                    : 'No hay servicios asociados ni elegibles para este cliente.'}
                            </div>
                        ) : (
                            <ServicePickerTable
                                candidates={eligibleServices.cleanCandidates}
                                blockedCandidates={
                                    eligibleServices.blockedCandidates
                                }
                                attachedCandidates={attachedServices}
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
                    <FieldFooter error={errors.issue_date} />
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
                    <FieldFooter error={errors.total_value}>
                        {isTotalLocked
                            ? `Calculado de ${servicesCount} servicio${servicesCount === 1 ? '' : 's'}.`
                            : showPicker && data.service_ids.length > 0
                              ? 'Sugerido por servicios — editable.'
                              : null}
                    </FieldFooter>
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
                    <FieldFooter error={errors.payment_status} />
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
                <FieldFooter error={errors.notes} />
            </div>
        </div>
    );
}
