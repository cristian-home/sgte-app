import { Head } from '@inertiajs/react';
import { Check, X } from 'lucide-react';
import { useState } from 'react';
import DocumentTypeController from '@/actions/App/Http/Controllers/DocumentTypeController';
import {
    DataTable,
    DataTableColumnHeader,
    DataTableRowActions,
} from '@/components/data-table';
import { DeleteConfirmationDialog } from '@/components/delete-confirmation-dialog';
import { PageHeader } from '@/components/page-header';
import AppLayout from '@/layouts/app-layout';
import type { ColumnDef } from '@tanstack/react-table';
import type { BreadcrumbItem, DocumentType } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Tipos de Documento',
        href: DocumentTypeController.index.url(),
    },
];

const columns: ColumnDef<DocumentType>[] = [
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
    {
        accessorKey: 'is_natural_person',
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="Persona Natural" />
        ),
        cell: ({ row }) =>
            row.getValue('is_natural_person') ? (
                <Check className="size-4 text-green-600" />
            ) : (
                <X className="size-4 text-muted-foreground" />
            ),
    },
    {
        accessorKey: 'is_legal_person',
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="Persona Juridica" />
        ),
        cell: ({ row }) =>
            row.getValue('is_legal_person') ? (
                <Check className="size-4 text-green-600" />
            ) : (
                <X className="size-4 text-muted-foreground" />
            ),
    },
];

export default function DocumentTypesIndex({
    documentTypes,
}: {
    documentTypes: DocumentType[];
}) {
    const [deleteUrl, setDeleteUrl] = useState<string | null>(null);

    const columnsWithActions: ColumnDef<DocumentType>[] = [
        ...columns,
        {
            id: 'actions',
            cell: ({ row }) => (
                <DataTableRowActions
                    editUrl={DocumentTypeController.edit.url(row.original.id)}
                    onDelete={() =>
                        setDeleteUrl(
                            DocumentTypeController.destroy.url(row.original.id),
                        )
                    }
                />
            ),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tipos de Documento" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <PageHeader
                    title="Tipos de Documento"
                    createUrl={DocumentTypeController.create.url()}
                    createLabel="Nuevo tipo"
                />
                <DataTable
                    columns={columnsWithActions}
                    data={documentTypes}
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
