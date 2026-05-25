import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import PensionFundController from '@/actions/App/Http/Controllers/PensionFundController';
import CatalogCodeNameDialog from '@/components/catalogs/catalog-code-name-dialog';
import {
    DataTable,
    DataTableColumnHeader,
    DataTableRowActions,
} from '@/components/data-table';
import { PageHeader } from '@/components/page-header';
import AppLayout from '@/layouts/app-layout';
import type { ColumnDef } from '@tanstack/react-table';
import type { BreadcrumbItem, PensionFund } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Fondos de Pensiones',
        href: PensionFundController.index.url(),
    },
];

const columns: ColumnDef<PensionFund>[] = [
    {
        accessorKey: 'code',
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="Código" />
        ),
    },
    {
        accessorKey: 'name',
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="Nombre" />
        ),
    },
];

export default function PensionFundsIndex({
    pensionFunds,
}: {
    pensionFunds: PensionFund[];
}) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [dialogMode, setDialogMode] = useState<'create' | 'edit'>('create');
    const [selected, setSelected] = useState<PensionFund | null>(null);

    function openCreate() {
        setSelected(null);
        setDialogMode('create');
        setDialogOpen(true);
    }

    function openEdit(record: PensionFund) {
        setSelected(record);
        setDialogMode('edit');
        setDialogOpen(true);
    }

    const columnsWithActions: ColumnDef<PensionFund>[] = [
        ...columns,
        {
            id: 'actions',
            cell: ({ row }) => (
                <DataTableRowActions
                    onEdit={() => openEdit(row.original)}
                    onDelete={() =>
                        router.delete(
                            PensionFundController.destroy.url(row.original.id),
                            { preserveScroll: true },
                        )
                    }
                />
            ),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Fondos de Pensiones" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <PageHeader
                    title="Fondos de Pensiones"
                    onCreate={openCreate}
                    createLabel="Nuevo fondo"
                />
                <DataTable
                    columns={columnsWithActions}
                    data={pensionFunds}
                    searchKey="name"
                    searchPlaceholder="Buscar por nombre..."
                />
            </div>
            <CatalogCodeNameDialog
                open={dialogOpen}
                onOpenChange={setDialogOpen}
                mode={dialogMode}
                record={selected}
                entityLabel="Fondo de Pensiones"
                storeUrl={PensionFundController.store.url()}
                updateUrl={(id) => PensionFundController.update.url(id)}
            />
        </AppLayout>
    );
}
