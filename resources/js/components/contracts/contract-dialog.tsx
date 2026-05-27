import { useForm, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
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
import { parseDueDate } from '@/lib/document-status';
import type { Contract } from '@/types/models';

/** Subset of a Contract needed to pre-fill the form in edit mode. */
export type EditableContract = Pick<
    Contract,
    | 'id'
    | 'contract_number'
    | 'third_party_id'
    | 'contract_object'
    | 'timezone'
    | 'start_date'
    | 'end_date'
    | 'route_description'
    | 'is_generic'
    | 'active'
    | 'billing_unit_type'
> & {
    third_party?: ThirdPartyOption | null;
};

type ContractFormState = ContractFormData & { _cascade: boolean };

interface ContractDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    mode: 'create' | 'edit';
    contract?: EditableContract | null;
    thirdParties: ThirdPartyOption[];
    /**
     * When both are provided, ContractForm renders a "+" button next to
     * the Cliente combobox that opens a nested ThirdPartyDialog.
     */
    documentTypes?: DocumentTypeOption[];
    municipalities?: MunicipalityOption[];
    /**
     * Create-mode only. When true, the backend flashes
     * `created_contract_id` and stays on the current page instead of
     * redirecting — used by the service form's nested contract flow.
     */
    cascade?: boolean;
}

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

export default function ContractDialog({
    open,
    onOpenChange,
    mode,
    contract,
    thirdParties,
    documentTypes,
    municipalities,
    cascade = false,
}: ContractDialogProps) {
    const sharedConfig = usePage().props.config as
        | { operation_tz?: string }
        | undefined;
    const operationTz = sharedConfig?.operation_tz ?? 'America/Bogota';

    const { data, setData, post, put, processing, errors, clearErrors } =
        useForm<ContractFormState>({
            contract_number: '',
            third_party_id: '',
            contract_object: 'business',
            timezone: operationTz,
            start_date: '',
            end_date: '',
            route_description: '',
            is_generic: false,
            active: true,
            billing_unit_type: '',
            _cascade: false,
        });

    // Re-seed the form whenever the dialog identity changes. Inertia's
    // `setData`/`clearErrors` aren't React state setters, so this effect
    // is not flagged by the React Compiler (same pattern as UserDialog).
    useEffect(() => {
        if (!open) {
            return;
        }
        if (mode === 'edit' && contract) {
            setData({
                contract_number: contract.contract_number,
                third_party_id: String(contract.third_party_id),
                contract_object: contract.contract_object,
                timezone: contract.timezone,
                start_date: toDateInput(contract.start_date),
                end_date: toDateInput(contract.end_date),
                route_description: contract.route_description ?? '',
                is_generic: contract.is_generic,
                active: contract.active,
                billing_unit_type: contract.billing_unit_type ?? '',
                _cascade: false,
            });
        } else {
            setData({
                contract_number: '',
                third_party_id: '',
                contract_object: 'business',
                timezone: operationTz,
                start_date: '',
                end_date: '',
                route_description: '',
                is_generic: false,
                active: true,
                billing_unit_type: '',
                _cascade: cascade,
            });
        }
        clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, mode, contract?.id, cascade]);

    // Keep the contract's current customer in the combobox even if it is
    // no longer flagged `is_customer = true`.
    const forceIncludeCustomer =
        mode === 'edit' && contract?.third_party
            ? [contract.third_party]
            : undefined;

    function submit(e: React.FormEvent<HTMLFormElement>) {
        e.preventDefault();
        // Defensive: this dialog renders its own <form>. Stop the submit event
        // from bubbling through the React tree to any ancestor <form> (e.g. the
        // service create page), which would submit that form too. See BUG-002.
        e.stopPropagation();
        if (mode === 'create') {
            post(ContractController.store().url, {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
            });
        } else if (contract) {
            put(ContractController.update(contract.id).url, {
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
                            ? 'Crear Contrato'
                            : 'Editar Contrato'}
                    </DialogTitle>
                    <DialogDescription>
                        {mode === 'create'
                            ? 'Complete los campos para registrar un nuevo contrato.'
                            : 'Actualice la información del contrato.'}
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
                            mode={mode}
                            forceIncludeCustomer={forceIncludeCustomer}
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
                            {mode === 'create' ? 'Guardar' : 'Actualizar'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
