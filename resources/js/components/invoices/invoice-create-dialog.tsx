import { useForm } from '@inertiajs/react';
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

interface InvoiceCreateDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    thirdParties: ThirdPartyOption[];
}

const initialData: InvoiceFormData = {
    third_party_id: '',
    invoice_number: '',
    total_value: '',
    issue_date: '',
    payment_status: 'pending',
    notes: '',
};

export default function InvoiceCreateDialog({
    open,
    onOpenChange,
    thirdParties,
}: InvoiceCreateDialogProps) {
    const { data, setData, post, processing, errors, reset } =
        useForm<InvoiceFormData>({ ...initialData });

    function submit(e: React.FormEvent<HTMLFormElement>) {
        e.preventDefault();
        post(InvoiceController.store().url, {
            onSuccess: () => {
                reset();
                onOpenChange(false);
            },
        });
    }

    function handleOpenChange(value: boolean) {
        if (!value) {
            reset();
        }
        onOpenChange(value);
    }

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="flex max-h-[calc(100vh-4rem)] flex-col px-0 sm:max-w-3xl">
                <DialogHeader className="px-6">
                    <DialogTitle>Crear Factura</DialogTitle>
                    <DialogDescription>
                        Complete los campos para registrar una nueva factura.
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
                            idPrefix="dlg"
                        />
                    </div>

                    <DialogFooter className="mt-4 gap-2 px-6">
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Cancelar
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={processing}>
                            Guardar
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
