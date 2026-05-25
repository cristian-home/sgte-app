import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
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
import type { Driver } from '@/types/models';

/** Subset of a Driver needed to pre-fill the form in edit mode. */
export type EditableDriver = Pick<
    Driver,
    | 'id'
    | 'document_type_id'
    | 'identification_number'
    | 'first_name'
    | 'second_name'
    | 'first_lastname'
    | 'second_lastname'
    | 'municipality_id'
    | 'address'
    | 'phone'
    | 'email'
    | 'license_category'
    | 'license_due_date'
    | 'eps_id'
    | 'pension_fund_id'
    | 'severance_fund_id'
    | 'has_social_security'
    | 'active'
>;

interface DriverDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    mode: 'create' | 'edit';
    driver?: EditableDriver | null;
    municipalities: MunicipalityOption[];
    documentTypes: DocumentTypeOption[];
    eps: CatalogOption[];
    pensionFunds: CatalogOption[];
    severanceFunds: CatalogOption[];
}

const emptyData: DriverFormData = {
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
    create_account: false,
    account_email: '',
};

function dataFromDriver(driver: EditableDriver): DriverFormData {
    return {
        document_type_id: driver.document_type_id
            ? String(driver.document_type_id)
            : '',
        identification_number: driver.identification_number,
        first_name: driver.first_name,
        second_name: driver.second_name ?? '',
        first_lastname: driver.first_lastname,
        second_lastname: driver.second_lastname ?? '',
        municipality_id: driver.municipality_id
            ? String(driver.municipality_id)
            : '',
        address: driver.address ?? '',
        phone: driver.phone ?? '',
        email: driver.email ?? '',
        license_category: driver.license_category,
        // Date inputs need 'Y-m-d'; the wall-clock accessor may carry a
        // time component depending on serialization — slice to be safe.
        license_due_date: driver.license_due_date
            ? driver.license_due_date.slice(0, 10)
            : '',
        eps_id: driver.eps_id ? String(driver.eps_id) : '',
        pension_fund_id: driver.pension_fund_id
            ? String(driver.pension_fund_id)
            : '',
        severance_fund_id: driver.severance_fund_id
            ? String(driver.severance_fund_id)
            : '',
        has_social_security: driver.has_social_security,
        active: driver.active,
        create_account: false,
        account_email: '',
    };
}

export default function DriverDialog({
    open,
    onOpenChange,
    mode,
    driver,
    municipalities,
    documentTypes,
    eps,
    pensionFunds,
    severanceFunds,
}: DriverDialogProps) {
    const { data, setData, post, put, processing, errors, clearErrors } =
        useForm<DriverFormData>({ ...emptyData });

    // Re-seed the form whenever the dialog identity changes. Inertia's
    // `setData`/`clearErrors` aren't React state setters, so this effect
    // is not flagged by the React Compiler (same pattern as UserDialog).
    useEffect(() => {
        if (!open) {
            return;
        }
        if (mode === 'edit' && driver) {
            setData(dataFromDriver(driver));
        } else {
            setData({ ...emptyData });
        }
        clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, mode, driver?.id]);

    function submit(e: React.FormEvent<HTMLFormElement>) {
        e.preventDefault();
        // This dialog owns its <form>; stop the submit event from bubbling
        // through the React tree to an ancestor <form>. See BUG-002.
        e.stopPropagation();
        if (mode === 'create') {
            post(DriverController.store().url, {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
            });
        } else if (driver) {
            put(DriverController.update(driver.id).url, {
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
                        {mode === 'create'
                            ? 'Crear Conductor'
                            : 'Editar Conductor'}
                    </DialogTitle>
                    <DialogDescription>
                        {mode === 'create'
                            ? 'Complete los campos para registrar un nuevo conductor.'
                            : 'Actualice la información del conductor.'}
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
                            mode={mode}
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
