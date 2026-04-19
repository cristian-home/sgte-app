import { Head, Link, useForm } from '@inertiajs/react';
import ContractController from '@/actions/App/Http/Controllers/ContractController';
import ContractForm, {
    type ContractFormData,
} from '@/components/contracts/contract-form';
import { type ThirdPartyOption } from '@/components/third-parties/third-party-combobox';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { parseDueDate } from '@/lib/document-status';
import contracts from '@/routes/contracts';

import type { BreadcrumbItem } from '@/types';
import type { Contract, DocumentType, ThirdParty } from '@/types/models';

type EditContract = Pick<
    Contract,
    | 'id'
    | 'contract_number'
    | 'third_party_id'
    | 'contract_object'
    | 'start_date'
    | 'end_date'
    | 'route_description'
    | 'is_generic'
    | 'active'
    | 'billing_unit_type'
> & {
    third_party?:
        | (Pick<
              ThirdParty,
              | 'id'
              | 'identification_number'
              | 'is_natural_person'
              | 'first_name'
              | 'first_lastname'
              | 'company_name'
              | 'is_customer'
              | 'is_provider'
          > & {
              document_type?: Pick<DocumentType, 'id' | 'code' | 'name'> | null;
          })
        | null;
};

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

export default function ContractsEdit({
    contract,
    thirdParties,
}: {
    contract: EditContract;
    thirdParties: ThirdPartyOption[];
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Contratos', href: contracts.index().url },
        {
            title: contract.contract_number,
            href: contracts.show(contract.id).url,
        },
        { title: 'Editar', href: contracts.edit(contract.id).url },
    ];

    const { data, setData, put, processing, errors } =
        useForm<ContractFormData>({
            contract_number: contract.contract_number,
            third_party_id: String(contract.third_party_id),
            contract_object: contract.contract_object,
            start_date: toDateInput(contract.start_date),
            end_date: toDateInput(contract.end_date),
            route_description: contract.route_description ?? '',
            is_generic: contract.is_generic,
            active: contract.active,
            billing_unit_type: contract.billing_unit_type ?? '',
        });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        put(ContractController.update(contract.id).url);
    }

    const forceIncludeCustomer = contract.third_party
        ? [contract.third_party]
        : [];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${contract.contract_number}`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Editar Contrato</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-6">
                            <ContractForm
                                data={data}
                                setData={setData}
                                errors={errors}
                                thirdParties={thirdParties}
                                forceIncludeCustomer={forceIncludeCustomer}
                            />

                            <div className="flex items-center gap-4">
                                <Button type="submit" disabled={processing}>
                                    Actualizar
                                </Button>
                                <Link href={contracts.index().url}>
                                    <Button type="button" variant="outline">
                                        Cancelar
                                    </Button>
                                </Link>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
