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

interface EditableDriver {
    id: number;
    document_type_id: number | null;
    identification_number: string;
    first_name: string;
    second_name: string | null;
    first_lastname: string;
    second_lastname: string | null;
    municipality_id: number | null;
    address: string;
    phone: string;
    email: string;
    license_category: string;
    license_due_date: string;
    eps_id: number | null;
    pension_fund_id: number | null;
    severance_fund_id: number | null;
    has_social_security: boolean;
    active: boolean;
}

export default function DriversEdit({
    driver,
    municipalities,
    documentTypes,
    eps,
    pensionFunds,
    severanceFunds,
}: {
    driver: EditableDriver;
    municipalities: MunicipalityOption[];
    documentTypes: DocumentTypeOption[];
    eps: CatalogOption[];
    pensionFunds: CatalogOption[];
    severanceFunds: CatalogOption[];
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Conductores', href: drivers.index().url },
        { title: 'Editar', href: DriverController.edit(driver.id).url },
    ];

    // Date inputs need 'Y-m-d'; the controller may return either form
    // depending on serialization. Slice the first 10 chars to be safe.
    const licenseDateForInput = driver.license_due_date
        ? driver.license_due_date.slice(0, 10)
        : '';

    const { data, setData, put, processing, errors } = useForm<DriverFormData>({
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
        address: driver.address,
        phone: driver.phone,
        email: driver.email,
        license_category: driver.license_category,
        license_due_date: licenseDateForInput,
        eps_id: driver.eps_id ? String(driver.eps_id) : '',
        pension_fund_id: driver.pension_fund_id
            ? String(driver.pension_fund_id)
            : '',
        severance_fund_id: driver.severance_fund_id
            ? String(driver.severance_fund_id)
            : '',
        has_social_security: driver.has_social_security,
        active: driver.active,
    });

    function submit(e: React.FormEvent<HTMLFormElement>) {
        e.preventDefault();
        put(DriverController.update(driver.id).url);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Editar Conductor" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Editar Conductor</CardTitle>
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
                                    Actualizar
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
