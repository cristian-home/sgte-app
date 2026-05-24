import { Head } from '@inertiajs/react';
import { PlusIcon } from 'lucide-react';
import { useMemo, useState } from 'react';
import { DataTable } from '@/components/data-table';
import InvoiceDialog, {
    type EditableInvoice,
} from '@/components/invoices/invoice-dialog';
import { paymentStatusRowTint } from '@/components/invoices/payment-status-pill';
import { type ThirdPartyOption } from '@/components/third-parties/third-party-combobox';
import { Button } from '@/components/ui/button';
import { useServerTable } from '@/hooks/use-server-table';
import AppLayout from '@/layouts/app-layout';
import invoices from '@/routes/invoices';

import { columns, type InvoiceRow, type InvoiceTableMeta } from './columns';

import type { Row } from '@tanstack/react-table';
import type { BreadcrumbItem, FilterDefinition, PaginatedData } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Facturas', href: invoices.index().url },
];

const STATIC_INVOICE_FILTERS: FilterDefinition[] = [
    {
        name: 'payment_status',
        label: 'Estado',
        options: [
            { value: 'pending', label: 'Pendiente' },
            { value: 'paid', label: 'Pagado' },
            { value: 'overdue', label: 'Vencido' },
        ],
    },
];

function rowTintFor(row: Row<InvoiceRow>): string | undefined {
    return paymentStatusRowTint(row.original);
}

export default function InvoicesIndex({
    invoices: paginatedInvoices,
    thirdParties,
}: {
    invoices: PaginatedData<InvoiceRow>;
    thirdParties: ThirdPartyOption[];
}) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [dialogMode, setDialogMode] = useState<'create' | 'edit'>('create');
    const [selectedInvoice, setSelectedInvoice] =
        useState<EditableInvoice | null>(null);

    function openCreate() {
        setSelectedInvoice(null);
        setDialogMode('create');
        setDialogOpen(true);
    }

    function openEdit(invoice: EditableInvoice) {
        setSelectedInvoice(invoice);
        setDialogMode('edit');
        setDialogOpen(true);
    }

    const tableMeta: InvoiceTableMeta = useMemo(
        () => ({ onEdit: openEdit }),
        [],
    );

    const invoiceFilters = useMemo<FilterDefinition[]>(
        () => [
            ...STATIC_INVOICE_FILTERS,
            {
                name: 'third_party_id',
                label: 'Cliente',
                options: thirdParties
                    .filter((tp) => tp.is_customer)
                    .map((tp) => ({
                        value: String(tp.id),
                        label: tp.is_natural_person
                            ? [tp.first_name, tp.first_lastname]
                                  .filter(Boolean)
                                  .join(' ') || '—'
                            : (tp.company_name ?? '—'),
                    })),
            },
        ],
        [thirdParties],
    );

    const {
        table,
        paginatedData,
        search,
        setSearch,
        loading,
        onNavigate,
        onPerPageChange,
        activeFilters,
        setFilter,
        clearFilters,
    } = useServerTable<InvoiceRow>({
        data: paginatedInvoices,
        columns,
        meta: tableMeta,
    });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Facturas" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <DataTable
                    table={table}
                    paginatedData={paginatedData}
                    search={search}
                    onSearchChange={setSearch}
                    loading={loading}
                    onNavigate={onNavigate}
                    onPerPageChange={onPerPageChange}
                    searchPlaceholder="Buscar por número de factura..."
                    filters={invoiceFilters}
                    activeFilters={activeFilters}
                    onFilterChange={setFilter}
                    onClearFilters={clearFilters}
                    getRowClassName={rowTintFor}
                    actions={
                        <Button onClick={openCreate} size="sm">
                            <PlusIcon className="mr-2 size-4" />
                            Crear Factura
                        </Button>
                    }
                />
            </div>

            <InvoiceDialog
                open={dialogOpen}
                onOpenChange={setDialogOpen}
                mode={dialogMode}
                invoice={selectedInvoice}
                thirdParties={thirdParties}
            />
        </AppLayout>
    );
}
