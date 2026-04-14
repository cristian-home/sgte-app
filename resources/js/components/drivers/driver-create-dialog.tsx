import { useForm } from '@inertiajs/react';
import DriverController from '@/actions/App/Http/Controllers/DriverController';
import DriverForm, {
    type CatalogOption,
    type DocumentTypeOption,
    type DriverFormData,
} from '@/components/drivers/driver-form';
import { type MunicipalityOption } from '@/components/municipality-combobox';
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

interface DriverCreateDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    municipalities: MunicipalityOption[];
    documentTypes: DocumentTypeOption[];
    eps: CatalogOption[];
    pensionFunds: CatalogOption[];
    severanceFunds: CatalogOption[];
}

const initialData: DriverFormData = {
    document_type_id: '',
    identification_number: '',
    first_name: '',
    second_name: '',
    first_lastname: '',
    second_lastname: '',
    municipality_id: '',
    address: '',
    phone: '',
    email: '',
    license_category: '',
    license_due_date: '',
    eps_id: '',
    pension_fund_id: '',
    severance_fund_id: '',
    has_social_security: true,
    active: true,
};

export default function DriverCreateDialog({
    open,
    onOpenChange,
    municipalities,
    documentTypes,
    eps,
    pensionFunds,
    severanceFunds,
}: DriverCreateDialogProps) {
    const { data, setData, post, processing, errors, reset } =
        useForm<DriverFormData>({ ...initialData });

    function submit(e: React.FormEvent<HTMLFormElement>) {
        e.preventDefault();
        post(DriverController.store().url, {
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
                    <DialogTitle>Crear Conductor</DialogTitle>
                    <DialogDescription>
                        Complete los campos para registrar un nuevo conductor.
                    </DialogDescription>
                </DialogHeader>

                <form
                    onSubmit={submit}
                    className="flex min-h-0 flex-1 flex-col"
                >
                    <div className="flex-1 space-y-6 overflow-y-auto px-6 py-2">
                        <DriverForm
                            data={data}
                            setData={setData}
                            errors={errors}
                            municipalities={municipalities}
                            documentTypes={documentTypes}
                            eps={eps}
                            pensionFunds={pensionFunds}
                            severanceFunds={severanceFunds}
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
