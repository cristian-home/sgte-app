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

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Terceros', href: thirdParties.index().url },
    { title: 'Crear', href: thirdParties.create().url },
];

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

export default function ThirdPartiesCreate({
    documentTypes,
    municipalities,
}: {
    documentTypes: DocumentTypeOption[];
    municipalities: MunicipalityOption[];
}) {
    const { data, setData, post, processing, errors } =
        useForm<ThirdPartyFormData>({ ...initialData });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post(ThirdPartyController.store().url);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Crear Tercero" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Crear Tercero</CardTitle>
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
                                    Guardar
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
