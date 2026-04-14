import { Head, Link, useForm } from '@inertiajs/react';
import InvoiceController from '@/actions/App/Http/Controllers/InvoiceController';
import InvoiceForm, {
    type InvoiceFormData,
} from '@/components/invoices/invoice-form';
import { type ThirdPartyOption } from '@/components/third-parties/third-party-combobox';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import invoices from '@/routes/invoices';

import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Facturas', href: invoices.index().url },
    { title: 'Crear', href: invoices.create().url },
];

const initialData: InvoiceFormData = {
    third_party_id: '',
    invoice_number: '',
    total_value: '',
    issue_date: '',
    payment_status: 'pending',
    notes: '',
};

export default function InvoicesCreate({
    thirdParties,
}: {
    thirdParties: ThirdPartyOption[];
}) {
    const { data, setData, post, processing, errors } =
        useForm<InvoiceFormData>({ ...initialData });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post(InvoiceController.store().url);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Crear Factura" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Crear Factura</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-6">
                            <InvoiceForm
                                data={data}
                                setData={setData}
                                errors={errors}
                                thirdParties={thirdParties}
                            />

                            <div className="flex items-center gap-4">
                                <Button type="submit" disabled={processing}>
                                    Guardar
                                </Button>
                                <Link href={invoices.index().url}>
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
