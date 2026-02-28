import { Head } from '@inertiajs/react';
import { useState } from 'react';
import SeveranceFundController from '@/actions/App/Http/Controllers/SeveranceFundController';
import {
    DataTable,
    DataTableColumnHeader,
    DataTableRowActions,
} from '@/components/data-table';
import { DeleteConfirmationDialog } from '@/components/delete-confirmation-dialog';
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

export default function SeveranceFundsIndex({
    severanceFunds,
}: {
    severanceFunds: SeveranceFund[];
}) {
    const [deleteUrl, setDeleteUrl] = useState<string | null>(null);

    const columnsWithActions: ColumnDef<SeveranceFund>[] = [
        ...columns,
        {
            id: 'actions',
            cell: ({ row }) => (
                <DataTableRowActions
                    editUrl={SeveranceFundController.edit.url(row.original.id)}
                    onDelete={() =>
                        setDeleteUrl(
                            SeveranceFundController.destroy.url(
                                row.original.id,
                            ),
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
                    createUrl={SeveranceFundController.create.url()}
                    createLabel="Nuevo fondo"
                />
                <DataTable
                    columns={columnsWithActions}
                    data={severanceFunds}
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
