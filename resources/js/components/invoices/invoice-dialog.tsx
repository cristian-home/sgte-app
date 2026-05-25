import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import InvoiceController from '@/actions/App/Http/Controllers/InvoiceController';
import InvoiceForm, {
    type InvoiceFormData,
} from '@/components/invoices/invoice-form';
import { type ThirdPartyOption } from '@/components/third-parties/third-party-combobox';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { parseDueDate } from '@/lib/document-status';
import type { Invoice } from '@/types/models';

/** Subset of an Invoice needed to pre-fill the form in edit mode. */
export type EditableInvoice = Pick<
    Invoice,
    | 'id'
    | 'invoice_number'
    | 'third_party_id'
    | 'total_value'
    | 'issue_date'
    | 'payment_status'
    | 'notes'
> & {
    services_count?: number;
    third_party?: ThirdPartyOption | null;
};

interface InvoiceDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    mode: 'create' | 'edit';
    invoice?: EditableInvoice | null;
    thirdParties: ThirdPartyOption[];
}

const emptyData: InvoiceFormData = {
    third_party_id: '',
    invoice_number: '',
    total_value: '',
    issue_date: '',
    payment_status: 'pending',
    notes: '',
};

/** Project a wall-clock date string onto a `Y-m-d` value for date inputs. */
function toDateInput(value: string | null): string {
    const parsed = parseDueDate(value);
    if (parsed === null) {
        return '';
    }
    const y = parsed.getFullYear();
    const m = String(parsed.getMonth() + 1).padStart(2, '0');
    const d = String(parsed.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

function dataFromInvoice(invoice: EditableInvoice): InvoiceFormData {
    return {
        third_party_id: String(invoice.third_party_id),
        invoice_number: invoice.invoice_number,
        total_value: String(invoice.total_value),
        issue_date: toDateInput(invoice.issue_date),
        payment_status: invoice.payment_status,
        notes: invoice.notes ?? '',
    };
}

export default function InvoiceDialog({
    open,
    onOpenChange,
    mode,
    invoice,
    thirdParties,
}: InvoiceDialogProps) {
    const { data, setData, post, put, processing, errors, clearErrors } =
        useForm<InvoiceFormData>({ ...emptyData });

    // Re-seed the form whenever the dialog identity changes. Inertia's
    // `setData`/`clearErrors` aren't React state setters, so this effect
    // is not flagged by the React Compiler (same pattern as UserDialog).
    useEffect(() => {
        if (!open) {
            return;
        }
        if (mode === 'edit' && invoice) {
            setData(dataFromInvoice(invoice));
        } else {
            setData({ ...emptyData });
        }
        clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, mode, invoice?.id]);

    // An invoice with attached services has a calculated total that the
    // form must not overwrite — lock the field when editing such a row.
    const servicesCount = invoice?.services_count ?? 0;
    const forceIncludeCustomer =
        mode === 'edit' && invoice?.third_party
            ? [invoice.third_party]
            : undefined;

    function submit(e: React.FormEvent<HTMLFormElement>) {
        e.preventDefault();
        // This dialog owns its <form>; stop the submit event from bubbling
        // through the React tree to an ancestor <form>. See BUG-002.
        e.stopPropagation();
        if (mode === 'create') {
            post(InvoiceController.store().url, {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
            });
        } else if (invoice) {
            put(InvoiceController.update(invoice.id).url, {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
            });
        }
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="flex max-h-[calc(100vh-4rem)] flex-col px-0 sm:max-w-3xl">
                <DialogHeader className="px-6">
                    <DialogTitle>
                        {mode === 'create' ? 'Crear Factura' : 'Editar Factura'}
                    </DialogTitle>
                    <DialogDescription>
                        {mode === 'create'
                            ? 'Complete los campos para registrar una nueva factura.'
                            : 'Actualice la información de la factura.'}
                    </DialogDescription>
                </DialogHeader>

                <form
                    onSubmit={submit}
                    className="flex min-h-0 flex-1 flex-col"
                >
                    <div className="flex-1 space-y-6 overflow-y-auto px-6 py-2">
                        <InvoiceForm
                            data={data}
                            setData={setData}
                            errors={errors}
                            thirdParties={thirdParties}
                            forceIncludeCustomer={forceIncludeCustomer}
                            idPrefix="dlg"
                            isTotalLocked={mode === 'edit' && servicesCount > 0}
                            servicesCount={servicesCount}
                        />
                    </div>

                    <DialogFooter className="mt-4 gap-2 px-6">
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Cancelar
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={processing}>
                            {mode === 'create' ? 'Guardar' : 'Actualizar'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
