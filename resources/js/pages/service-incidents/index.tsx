import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import ServiceIncidentController from '@/actions/App/Http/Controllers/ServiceIncidentController';
import {
    DataTable,
    DataTableColumnHeader,
    DataTableRowActions,
} from '@/components/data-table';
import { DeleteConfirmationDialog } from '@/components/delete-confirmation-dialog';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import type { ColumnDef } from '@tanstack/react-table';
import type { BreadcrumbItem, ServiceIncident } from '@/types';

function formatTimestamp(reportedAt: string | null): string {
    if (!reportedAt) return '\u2014';
    const ms = Number(reportedAt) * 1000;
    if (isNaN(ms)) return '\u2014';
    return new Intl.DateTimeFormat('es-CO', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(ms));
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Novedades',
        href: ServiceIncidentController.index.url(),
    },
];

const columns: ColumnDef<ServiceIncident>[] = [
    {
        accessorKey: 'service',
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="Servicio" />
        ),
        cell: ({ row }) => {
            const service = row.original.service;
            return service?.vehicle?.plate ?? `#${row.original.service_id}`;
        },
    },
    {
        accessorKey: 'incident_type',
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="Tipo" />
        ),
        cell: ({ row }) => row.original.incident_type?.name ?? '\u2014',
    },
    {
        accessorKey: 'description',
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="Descripción" />
        ),
        cell: ({ row }) => (
            <span
                className="block max-w-[200px] truncate"
                title={row.original.description}
            >
                {row.original.description}
            </span>
        ),
    },
    {
        accessorKey: 'reported_at',
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="Fecha Reporte" />
        ),
        cell: ({ row }) => (
            <span className="whitespace-nowrap">
                {formatTimestamp(row.original.reported_at)}
            </span>
        ),
    },
    {
        accessorKey: 'registrar',
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="Registrador" />
        ),
        cell: ({ row }) => row.original.registrar?.name ?? '\u2014',
    },
    {
        accessorKey: 'affects_billing',
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="Facturación" />
        ),
        cell: ({ row }) =>
            row.original.affects_billing ? (
                <Badge variant="destructive">Afecta</Badge>
            ) : null,
    },
];

export default function ServiceIncidentsIndex({
    serviceIncidents,
}: {
    serviceIncidents: ServiceIncident[];
}) {
    const [deleteUrl, setDeleteUrl] = useState<string | null>(null);

    const columnsWithActions: ColumnDef<ServiceIncident>[] = [
        ...columns,
        {
            id: 'actions',
            cell: ({ row }) => (
                <DataTableRowActions
                    editUrl={ServiceIncidentController.edit.url(
                        row.original.id,
                    )}
                    onDelete={() =>
                        setDeleteUrl(
                            ServiceIncidentController.destroy.url(
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
            <Head title="Novedades" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <PageHeader title="Novedades" />
                <DataTable
                    columns={columnsWithActions}
                    data={serviceIncidents}
                    searchKey="description"
                    searchPlaceholder="Buscar por descripción..."
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
