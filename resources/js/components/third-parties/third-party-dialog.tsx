import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
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
import type { ThirdParty } from '@/types/models';

/** Subset of a ThirdParty needed to pre-fill the form in edit mode. */
export type EditableThirdParty = Pick<
    ThirdParty,
    | 'id'
    | 'document_type_id'
    | 'identification_number'
    | 'is_natural_person'
    | 'first_name'
    | 'second_name'
    | 'first_lastname'
    | 'second_lastname'
    | 'company_name'
    | 'trade_name'
    | 'municipality_id'
    | 'address'
    | 'phone'
    | 'email'
    | 'is_customer'
    | 'is_provider'
    | 'active'
>;

type ThirdPartyFormState = ThirdPartyFormData & { _cascade: boolean };

interface ThirdPartyDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    mode: 'create' | 'edit';
    thirdParty?: EditableThirdParty | null;
    documentTypes: DocumentTypeOption[];
    municipalities: MunicipalityOption[];
    /**
     * Create-mode only. When true, the backend flashes
     * `created_third_party_id` and stays on the current page instead of
     * redirecting — used by the nested "+" dialog inside ContractForm.
     */
    cascade?: boolean;
}

const emptyData: ThirdPartyFormState = {
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
    _cascade: false,
};

function dataFromThirdParty(
    thirdParty: EditableThirdParty,
): ThirdPartyFormState {
    return {
        document_type_id: String(thirdParty.document_type_id),
        identification_number: thirdParty.identification_number,
        is_natural_person: thirdParty.is_natural_person,
        first_name: thirdParty.first_name ?? '',
        second_name: thirdParty.second_name ?? '',
        first_lastname: thirdParty.first_lastname ?? '',
        second_lastname: thirdParty.second_lastname ?? '',
        company_name: thirdParty.company_name ?? '',
        trade_name: thirdParty.trade_name ?? '',
        municipality_id: thirdParty.municipality_id
            ? String(thirdParty.municipality_id)
            : '',
        address: thirdParty.address ?? '',
        phone: thirdParty.phone ?? '',
        email: thirdParty.email ?? '',
        is_customer: thirdParty.is_customer,
        is_provider: thirdParty.is_provider,
        active: thirdParty.active,
        _cascade: false,
    };
}

export default function ThirdPartyDialog({
    open,
    onOpenChange,
    mode,
    thirdParty,
    documentTypes,
    municipalities,
    cascade = false,
}: ThirdPartyDialogProps) {
    const { data, setData, post, put, processing, errors, clearErrors } =
        useForm<ThirdPartyFormState>({ ...emptyData });

    // Re-seed the form whenever the dialog identity changes. Inertia's
    // `setData`/`clearErrors` aren't React state setters, so this effect
    // is not flagged by the React Compiler (same pattern as UserDialog).
    useEffect(() => {
        if (!open) {
            return;
        }
        if (mode === 'edit' && thirdParty) {
            setData(dataFromThirdParty(thirdParty));
        } else {
            setData({ ...emptyData, _cascade: cascade });
        }
        clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, mode, thirdParty?.id, cascade]);

    function submit(e: React.FormEvent<HTMLFormElement>) {
        e.preventDefault();
        // This dialog owns its <form>; stop the submit event from bubbling
        // through the React tree to an ancestor <form>. See BUG-002.
        e.stopPropagation();
        if (mode === 'create') {
            post(ThirdPartyController.store().url, {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
            });
        } else if (thirdParty) {
            put(ThirdPartyController.update(thirdParty.id).url, {
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
                        {mode === 'create' ? 'Crear Tercero' : 'Editar Tercero'}
                    </DialogTitle>
                    <DialogDescription>
                        {mode === 'create'
                            ? 'Complete los campos para registrar un nuevo tercero.'
                            : 'Actualice la información del tercero.'}
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
                            {mode === 'create' ? 'Guardar' : 'Actualizar'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
