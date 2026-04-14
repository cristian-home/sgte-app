import { Head, Link, useForm } from '@inertiajs/react';
import InvoiceController from '@/actions/App/Http/Controllers/InvoiceController';
import InvoiceForm from '@/components/invoices/invoice-form';
import { type InvoiceFormData } from '@/components/invoices/invoice-form';
import { type ThirdPartyOption } from '@/components/third-parties/third-party-combobox';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { parseDueDate } from '@/lib/document-status';
import invoices from '@/routes/invoices';

import type { BreadcrumbItem } from '@/types';
import type { DocumentType, Invoice, ThirdParty } from '@/types/models';

type EditInvoice = Pick<
    Invoice,
    | 'id'
    | 'invoice_number'
    | 'third_party_id'
    | 'total_value'
    | 'issue_date'
    | 'payment_status'
    | 'notes'
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

export default function InvoicesEdit({
    invoice,
    thirdParties,
}: {
    invoice: EditInvoice;
    thirdParties: ThirdPartyOption[];
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Facturas', href: invoices.index().url },
        {
            title: invoice.invoice_number,
            href: invoices.show(invoice.id).url,
        },
        { title: 'Editar', href: invoices.edit(invoice.id).url },
    ];

    const { data, setData, put, processing, errors } = useForm<InvoiceFormData>(
        {
            third_party_id: String(invoice.third_party_id),
            invoice_number: invoice.invoice_number,
            total_value: String(invoice.total_value),
            issue_date: toDateInput(invoice.issue_date),
            payment_status: invoice.payment_status,
            notes: invoice.notes ?? '',
        },
    );

    function submit(e: React.FormEvent) {
        e.preventDefault();
        put(InvoiceController.update(invoice.id).url);
    }

    const forceIncludeCustomer = invoice.third_party
        ? [invoice.third_party]
        : [];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${invoice.invoice_number}`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Editar Factura</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-6">
                            <InvoiceForm
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
