import { useForm, usePage } from '@inertiajs/react';
import ContractController from '@/actions/App/Http/Controllers/ContractController';
import ContractForm, {
    type ContractFormData,
} from '@/components/contracts/contract-form';
import { type MunicipalityOption } from '@/components/municipality-combobox';
import { type ThirdPartyOption } from '@/components/third-parties/third-party-combobox';
import { type DocumentTypeOption } from '@/components/third-parties/third-party-form';
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

interface ContractCreateDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    thirdParties: ThirdPartyOption[];
    /**
     * When provided, ContractForm renders a "+" button next to the
     * Cliente combobox that opens a nested ThirdPartyCreateDialog.
     * Both props are required for the nested dialog to render.
     */
    documentTypes?: DocumentTypeOption[];
    municipalities?: MunicipalityOption[];
}

export default function ContractCreateDialog({
    open,
    onOpenChange,
    thirdParties,
    documentTypes,
    municipalities,
}: ContractCreateDialogProps) {
    const sharedConfig = usePage().props.config as
        | { operation_tz?: string }
        | undefined;
    const initialData = {
        contract_number: '',
        third_party_id: '',
        contract_object: 'business',
        timezone: sharedConfig?.operation_tz ?? 'America/Bogota',
        start_date: '',
        end_date: '',
        route_description: '',
        is_generic: false,
        active: true,
        billing_unit_type: '',
        // Cascade flag — read by ContractController::store. Tells the
        // backend to flash `created_contract_id` and `back()` instead of
        // redirecting to /contracts/index. The parent form (e.g. service
        // create) reads the flash and auto-selects.
        _cascade: true as boolean,
    };
    const { data, setData, post, processing, errors, reset } = useForm<
        ContractFormData & { _cascade: boolean }
    >({ ...initialData });

    function submit(e: React.FormEvent<HTMLFormElement>) {
        e.preventDefault();
        post(ContractController.store().url, {
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
                    <DialogTitle>Crear Contrato</DialogTitle>
                    <DialogDescription>
                        Complete los campos para registrar un nuevo contrato.
                    </DialogDescription>
                </DialogHeader>

                <form
                    onSubmit={submit}
                    className="flex min-h-0 flex-1 flex-col"
                >
                    <div className="flex-1 space-y-6 overflow-y-auto px-6 py-2">
                        <ContractForm
                            data={data}
                            setData={setData}
                            errors={errors}
                            thirdParties={thirdParties}
                            idPrefix="dlg"
                            allowCreateThirdParty={
                                !!documentTypes && !!municipalities
                            }
                            documentTypes={documentTypes}
                            municipalities={municipalities}
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
