import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import EpsController from '@/actions/App/Http/Controllers/EpsController';
import CatalogCodeNameDialog from '@/components/catalogs/catalog-code-name-dialog';
import {
    DataTable,
    DataTableColumnHeader,
    DataTableRowActions,
} from '@/components/data-table';
import { PageHeader } from '@/components/page-header';
import AppLayout from '@/layouts/app-layout';
import type { ColumnDef } from '@tanstack/react-table';
import type { BreadcrumbItem, Eps } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'EPS',
        href: EpsController.index.url(),
    },
];

const columns: ColumnDef<Eps>[] = [
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

export default function EpsIndex({ eps }: { eps: Eps[] }) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [dialogMode, setDialogMode] = useState<'create' | 'edit'>('create');
    const [selected, setSelected] = useState<Eps | null>(null);

    function openCreate() {
        setSelected(null);
        setDialogMode('create');
        setDialogOpen(true);
    }

    function openEdit(record: Eps) {
        setSelected(record);
        setDialogMode('edit');
        setDialogOpen(true);
    }

    const columnsWithActions: ColumnDef<Eps>[] = [
        ...columns,
        {
            id: 'actions',
            cell: ({ row }) => (
                <DataTableRowActions
                    onEdit={() => openEdit(row.original)}
                    onDelete={() =>
                        router.delete(
                            EpsController.destroy.url(row.original.id),
                            { preserveScroll: true },
                        )
                    }
                />
            ),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="EPS" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <PageHeader
                    title="EPS"
                    onCreate={openCreate}
                    createLabel="Nueva EPS"
                />
                <DataTable
                    columns={columnsWithActions}
                    data={eps}
                    searchKey="name"
                    searchPlaceholder="Buscar por nombre..."
                />
            </div>
            <CatalogCodeNameDialog
                open={dialogOpen}
                onOpenChange={setDialogOpen}
                mode={dialogMode}
                record={selected}
                entityLabel="EPS"
                storeUrl={EpsController.store.url()}
                updateUrl={(id) => EpsController.update.url(id)}
            />
        </AppLayout>
    );
}
