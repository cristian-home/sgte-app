import { Head } from '@inertiajs/react';
import { useState } from 'react';
import PensionFundController from '@/actions/App/Http/Controllers/PensionFundController';
import {
    DataTable,
    DataTableColumnHeader,
    DataTableRowActions,
} from '@/components/data-table';
import { DeleteConfirmationDialog } from '@/components/delete-confirmation-dialog';
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

export default function PensionFundsIndex({
    pensionFunds,
}: {
    pensionFunds: PensionFund[];
}) {
    const [deleteUrl, setDeleteUrl] = useState<string | null>(null);

    const columnsWithActions: ColumnDef<PensionFund>[] = [
        ...columns,
        {
            id: 'actions',
            cell: ({ row }) => (
                <DataTableRowActions
                    editUrl={PensionFundController.edit.url(row.original.id)}
                    onDelete={() =>
                        setDeleteUrl(
                            PensionFundController.destroy.url(row.original.id),
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
                    createUrl={PensionFundController.create.url()}
                    createLabel="Nuevo fondo"
                />
                <DataTable
                    columns={columnsWithActions}
                    data={pensionFunds}
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
