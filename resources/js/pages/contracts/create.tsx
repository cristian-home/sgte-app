import { Head, Link, useForm } from '@inertiajs/react';
import ContractController from '@/actions/App/Http/Controllers/ContractController';
import ContractForm, {
    type ContractFormData,
} from '@/components/contracts/contract-form';
import { type ThirdPartyOption } from '@/components/third-parties/third-party-combobox';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import contracts from '@/routes/contracts';

import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Contratos', href: contracts.index().url },
    { title: 'Crear', href: contracts.create().url },
];

const initialData: ContractFormData = {
    contract_number: '',
    third_party_id: '',
    contract_object: 'business',
    start_date: '',
    end_date: '',
    route_description: '',
    is_generic: false,
    active: true,
    billing_unit_type: '',
};

export default function ContractsCreate({
    thirdParties,
}: {
    thirdParties: ThirdPartyOption[];
}) {
    const { data, setData, post, processing, errors } =
        useForm<ContractFormData>({ ...initialData });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post(ContractController.store().url);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Crear Contrato" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Crear Contrato</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-6">
                            <ContractForm
                                data={data}
                                setData={setData}
                                errors={errors}
                                thirdParties={thirdParties}
                            />

                            <div className="flex items-center gap-4">
                                <Button type="submit" disabled={processing}>
                                    Guardar
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
