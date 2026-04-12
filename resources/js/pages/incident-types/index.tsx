import { Head } from '@inertiajs/react';
import { Check, X } from 'lucide-react';
import { useState } from 'react';
import IncidentTypeController from '@/actions/App/Http/Controllers/IncidentTypeController';
import {
    DataTable,
    DataTableColumnHeader,
    DataTableRowActions,
} from '@/components/data-table';
import { DeleteConfirmationDialog } from '@/components/delete-confirmation-dialog';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import {
    IncidentSeverityLabel,
    type IncidentSeverity,
} from '@/enums/IncidentSeverity';
import AppLayout from '@/layouts/app-layout';
import type { ColumnDef } from '@tanstack/react-table';
import type { BreadcrumbItem, IncidentType } from '@/types';

const severityVariant: Record<
    IncidentSeverity,
    'default' | 'secondary' | 'destructive'
> = {
    informational: 'secondary',
    minor: 'default',
    major: 'destructive',
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Tipos de Novedad',
        href: IncidentTypeController.index.url(),
    },
];

const columns: ColumnDef<IncidentType>[] = [
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
        accessorKey: 'severity',
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="Severidad" />
        ),
        cell: ({ row }) => {
            const severity = row.getValue('severity') as IncidentSeverity;
            return (
                <Badge variant={severityVariant[severity]}>
                    {IncidentSeverityLabel[severity]}
                </Badge>
            );
        },
    },
    {
        accessorKey: 'affects_billing_default',
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="Afecta Facturación" />
        ),
        cell: ({ row }) =>
            row.getValue('affects_billing_default') ? (
                <Check className="size-4 text-green-600" />
            ) : (
                <X className="size-4 text-muted-foreground" />
            ),
    },
];

export default function IncidentTypesIndex({
    incidentTypes,
}: {
    incidentTypes: IncidentType[];
}) {
    const [deleteUrl, setDeleteUrl] = useState<string | null>(null);

    const columnsWithActions: ColumnDef<IncidentType>[] = [
        ...columns,
        {
            id: 'actions',
            cell: ({ row }) => (
                <DataTableRowActions
                    editUrl={IncidentTypeController.edit.url(row.original.id)}
                    onDelete={() =>
                        setDeleteUrl(
                            IncidentTypeController.destroy.url(row.original.id),
                        )
                    }
                />
            ),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tipos de Novedad" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <PageHeader
                    title="Tipos de Novedad"
                    createUrl={IncidentTypeController.create.url()}
                    createLabel="Nuevo Tipo de Novedad"
                />
                <DataTable
                    columns={columnsWithActions}
                    data={incidentTypes}
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
