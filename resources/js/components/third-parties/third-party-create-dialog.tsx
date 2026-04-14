import { useForm } from '@inertiajs/react';
import ThirdPartyController from '@/actions/App/Http/Controllers/ThirdPartyController';
import { type MunicipalityOption } from '@/components/municipality-combobox';
import ThirdPartyForm, {
    type DocumentTypeOption,
    type ThirdPartyFormData,
} from '@/components/third-parties/third-party-form';
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

interface ThirdPartyCreateDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    documentTypes: DocumentTypeOption[];
    municipalities: MunicipalityOption[];
}

const initialData: ThirdPartyFormData = {
    document_type_id: '',
    identification_number: '',
    is_natural_person: true,
    first_name: '',
    second_name: '',
    first_lastname: '',
    second_lastname: '',
    company_name: '',
    trade_name: '',
    municipality_id: '',
    address: '',
    phone: '',
    email: '',
    is_customer: true,
    is_provider: false,
    active: true,
};

export default function ThirdPartyCreateDialog({
    open,
    onOpenChange,
    documentTypes,
    municipalities,
}: ThirdPartyCreateDialogProps) {
    const { data, setData, post, processing, errors, reset } =
        useForm<ThirdPartyFormData>({ ...initialData });

    function submit(e: React.FormEvent<HTMLFormElement>) {
        e.preventDefault();
        post(ThirdPartyController.store().url, {
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
                    <DialogTitle>Crear Tercero</DialogTitle>
                    <DialogDescription>
                        Complete los campos para registrar un nuevo tercero.
                    </DialogDescription>
                </DialogHeader>

                <form
                    onSubmit={submit}
                    className="flex min-h-0 flex-1 flex-col"
                >
                    <div className="flex-1 space-y-6 overflow-y-auto px-6 py-2">
                        <ThirdPartyForm
                            data={data}
                            setData={setData}
                            errors={errors}
                            documentTypes={documentTypes}
                            municipalities={municipalities}
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
