import { Head, Link, useForm } from '@inertiajs/react';
import ThirdPartyController from '@/actions/App/Http/Controllers/ThirdPartyController';
import { type MunicipalityOption } from '@/components/municipality-combobox';
import ThirdPartyForm, {
    type DocumentTypeOption,
    type ThirdPartyFormData,
} from '@/components/third-parties/third-party-form';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import thirdParties from '@/routes/third-parties';

import type { BreadcrumbItem } from '@/types';

interface ThirdParty {
    id: number;
    document_type_id: number;
    identification_number: string;
    is_natural_person: boolean;
    first_name: string | null;
    second_name: string | null;
    first_lastname: string | null;
    second_lastname: string | null;
    company_name: string | null;
    trade_name: string | null;
    municipality_id: number | null;
    address: string;
    phone: string;
    email: string;
    is_customer: boolean;
    is_provider: boolean;
    active: boolean;
}

export default function ThirdPartiesEdit({
    thirdParty,
    documentTypes,
    municipalities,
}: {
    thirdParty: ThirdParty;
    documentTypes: DocumentTypeOption[];
    municipalities: MunicipalityOption[];
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Terceros', href: thirdParties.index().url },
        {
            title: 'Editar',
            href: ThirdPartyController.edit(thirdParty.id).url,
        },
    ];

    const { data, setData, put, processing, errors } =
        useForm<ThirdPartyFormData>({
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
            address: thirdParty.address,
            phone: thirdParty.phone,
            email: thirdParty.email,
            is_customer: thirdParty.is_customer,
            is_provider: thirdParty.is_provider,
            active: thirdParty.active,
        });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        put(ThirdPartyController.update(thirdParty.id).url);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Editar Tercero" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Editar Tercero</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-6">
                            <ThirdPartyForm
                                data={data}
                                setData={setData}
                                errors={errors}
                                documentTypes={documentTypes}
                                municipalities={municipalities}
                            />

                            <div className="flex items-center gap-4">
                                <Button type="submit" disabled={processing}>
                                    Actualizar
                                </Button>
                                <Link href={thirdParties.index().url}>
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
