import { Head, Link, useForm } from '@inertiajs/react';
import DriverController from '@/actions/App/Http/Controllers/DriverController';
import DriverForm, {
    type CatalogOption,
    type DocumentTypeOption,
    type DriverFormData,
} from '@/components/drivers/driver-form';
import { type MunicipalityOption } from '@/components/municipality-combobox';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import drivers from '@/routes/drivers';

import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Conductores', href: drivers.index().url },
    { title: 'Crear', href: drivers.create().url },
];

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

export default function DriversCreate({
    municipalities,
    documentTypes,
    eps,
    pensionFunds,
    severanceFunds,
}: {
    municipalities: MunicipalityOption[];
    documentTypes: DocumentTypeOption[];
    eps: CatalogOption[];
    pensionFunds: CatalogOption[];
    severanceFunds: CatalogOption[];
}) {
    const { data, setData, post, processing, errors } =
        useForm<DriverFormData>({ ...initialData });

    function submit(e: React.FormEvent<HTMLFormElement>) {
        e.preventDefault();
        post(DriverController.store().url);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Crear Conductor" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Crear Conductor</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-6">
                            <DriverForm
                                data={data}
                                setData={setData}
                                errors={errors}
                                municipalities={municipalities}
                                documentTypes={documentTypes}
                                eps={eps}
                                pensionFunds={pensionFunds}
                                severanceFunds={severanceFunds}
                            />

                            <div className="flex items-center gap-4">
                                <Button type="submit" disabled={processing}>
                                    Guardar
                                </Button>
                                <Link href={drivers.index().url}>
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
