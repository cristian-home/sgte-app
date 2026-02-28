import { Head } from '@inertiajs/react';
import { useState } from 'react';
import EpsController from '@/actions/App/Http/Controllers/EpsController';
import {
    DataTable,
    DataTableColumnHeader,
    DataTableRowActions,
} from '@/components/data-table';
import { DeleteConfirmationDialog } from '@/components/delete-confirmation-dialog';
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
            <DataTableColumnHeader column={column} title="Codigo" />
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
    const [deleteUrl, setDeleteUrl] = useState<string | null>(null);

    const columnsWithActions: ColumnDef<Eps>[] = [
        ...columns,
        {
            id: 'actions',
            cell: ({ row }) => (
                <DataTableRowActions
                    editUrl={EpsController.edit.url(row.original.id)}
                    onDelete={() =>
                        setDeleteUrl(EpsController.destroy.url(row.original.id))
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
                    createUrl={EpsController.create.url()}
                    createLabel="Nueva EPS"
                />
                <DataTable
                    columns={columnsWithActions}
                    data={eps}
                    searchKey="name"
                    searchPlaceholder="Buscar por nombre..."
                />
            </div>
            <DeleteConfirmationDialog
                open={deleteUrl !== null}
                onOpenChange={(open) => !open && setDeleteUrl(null)}
                deleteUrl={deleteUrl ?? ''}
            />
        </AppLayout>
    );
}
