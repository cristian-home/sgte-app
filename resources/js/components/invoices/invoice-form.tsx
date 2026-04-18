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

export interface InvoiceFormData {
    third_party_id: string;
    invoice_number: string;
    total_value: string;
    issue_date: string;
    payment_status: string;
    notes: string;
}

interface InvoiceFormProps {
    data: InvoiceFormData;
    setData: <K extends keyof InvoiceFormData>(
        key: K,
        value: InvoiceFormData[K],
    ) => void;
    errors: Partial<Record<keyof InvoiceFormData, string>>;
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
}: InvoiceFormProps) {
    const id = (name: string) => (idPrefix ? `${idPrefix}_${name}` : name);
    const invalid = (field: keyof InvoiceFormData) =>
        errors[field] ? true : undefined;

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
                        onChange={(value) => setData('third_party_id', value)}
                        invalid={invalid('third_party_id')}
                        placeholder="Selecciona un cliente"
                    />
                    <InputError message={errors.third_party_id} />
                </div>
            </div>

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
                    <div className="relative">
                        <span className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-muted-foreground">
                            $
                        </span>
                        <Input
                            id={id('total_value')}
                            type="number"
                            step="0.01"
                            min="0.01"
                            value={data.total_value}
                            readOnly={isTotalLocked}
                            aria-invalid={invalid('total_value')}
                            onChange={(e) =>
                                setData('total_value', e.target.value)
                            }
                            className="pl-7 tabular-nums"
                        />
                    </div>
                    {isTotalLocked && (
                        <p className="text-xs text-muted-foreground italic">
                            (calculado automáticamente — hay {servicesCount}{' '}
                            servicio
                            {servicesCount === 1 ? '' : 's'} asociado
                            {servicesCount === 1 ? '' : 's'})
                        </p>
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
