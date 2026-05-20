import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import SeveranceFundController from '@/actions/App/Http/Controllers/SeveranceFundController';
import CatalogCodeNameDialog from '@/components/catalogs/catalog-code-name-dialog';
import {
    DataTable,
    DataTableColumnHeader,
    DataTableRowActions,
} from '@/components/data-table';
import { PageHeader } from '@/components/page-header';
import AppLayout from '@/layouts/app-layout';
import type { ColumnDef } from '@tanstack/react-table';
import type { BreadcrumbItem, SeveranceFund } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Fondos de Cesantias',
        href: SeveranceFundController.index.url(),
    },
];

const columns: ColumnDef<SeveranceFund>[] = [
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

export default function SeveranceFundsIndex({
    severanceFunds,
}: {
    severanceFunds: SeveranceFund[];
}) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [dialogMode, setDialogMode] = useState<'create' | 'edit'>('create');
    const [selected, setSelected] = useState<SeveranceFund | null>(null);

    function openCreate() {
        setSelected(null);
        setDialogMode('create');
        setDialogOpen(true);
    }

    function openEdit(record: SeveranceFund) {
        setSelected(record);
        setDialogMode('edit');
        setDialogOpen(true);
    }

    const columnsWithActions: ColumnDef<SeveranceFund>[] = [
        ...columns,
        {
            id: 'actions',
            cell: ({ row }) => (
                <DataTableRowActions
                    onEdit={() => openEdit(row.original)}
                    onDelete={() =>
                        router.delete(
                            SeveranceFundController.destroy.url(
                                row.original.id,
                            ),
                            { preserveScroll: true },
                        )
                    }
                />
            ),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Fondos de Cesantias" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <PageHeader
                    title="Fondos de Cesantias"
                    onCreate={openCreate}
                    createLabel="Nuevo fondo"
                />
                <DataTable
                    columns={columnsWithActions}
                    data={severanceFunds}
                    searchKey="name"
                    searchPlaceholder="Buscar por nombre..."
                />
            </div>
            <CatalogCodeNameDialog
                open={dialogOpen}
                onOpenChange={setDialogOpen}
                mode={dialogMode}
                record={selected}
                entityLabel="Fondo de Cesantías"
                storeUrl={SeveranceFundController.store.url()}
                updateUrl={(id) => SeveranceFundController.update.url(id)}
            />
        </AppLayout>
    );
}
