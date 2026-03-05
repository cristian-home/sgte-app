import { Link, router } from '@inertiajs/react';
import { Can } from '@/components/can';
import {
    DataTableColumnHeader,
    DataTableRowActions,
} from '@/components/data-table';
import { Badge } from '@/components/ui/badge';
import { Permission } from '@/enums/Permission';
import services from '@/routes/services';

import type { ColumnDef } from '@tanstack/react-table';
import type { Service } from '@/types';

const statusLabels: Record<string, string> = {
    open: 'Abierto',
    closed: 'Cerrado',
};

const paymentMethodLabels: Record<string, string> = {
    cash: 'Efectivo',
    credit: 'Crédito',
    transfer: 'Transferencia',
};

function formatCurrency(value: string | number): string {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0,
    }).format(Number(value));
}

function formatDate(date: string): string {
    return new Intl.DateTimeFormat('es-CO', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    }).format(new Date(date));
}

export const columns: ColumnDef<Service, unknown>[] = [
    {
        accessorKey: 'service_date',
        meta: { label: 'Fecha' },
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="Fecha" />
        ),
        cell: ({ row }) => (
            <Link
                href={services.show(row.original.id).url}
                className="text-primary hover:underline"
            >
                {formatDate(row.original.service_date)}
            </Link>
        ),
    },
    {
        id: 'route',
        meta: { label: 'Ruta' },
        header: 'Ruta',
        cell: ({ row }) => (
            <div className="flex flex-col">
                <span className="font-medium">
                    {row.original.origin_address ?? '—'}
                </span>
                <span className="text-xs text-muted-foreground">
                    → {row.original.destination_address ?? '—'}
                </span>
            </div>
        ),
    },
    {
        id: 'vehicle',
        meta: { label: 'Vehículo' },
        header: 'Vehículo',
        cell: ({ row }) =>
            row.original.vehicle?.plate ?? (
                <span className="text-muted-foreground">—</span>
            ),
    },
    {
        id: 'driver',
        meta: { label: 'Conductor' },
        header: 'Conductor',
        cell: ({ row }) => {
            const driver = row.original.driver;
            if (!driver) {
                return <span className="text-muted-foreground">—</span>;
            }
            return `${driver.first_name} ${driver.first_lastname}`;
        },
    },
    {
        accessorKey: 'unit_value',
        meta: { label: 'Valor' },
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="Valor" />
        ),
        cell: ({ row }) => (
            <span className="tabular-nums">
                {formatCurrency(row.original.unit_value)}
            </span>
        ),
    },
    {
        accessorKey: 'service_status',
        meta: { label: 'Estado' },
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="Estado" />
        ),
        cell: ({ row }) => {
            const status = row.original.service_status;
            return (
                <Badge variant={status === 'open' ? 'secondary' : 'default'}>
                    {statusLabels[status] ?? status}
                </Badge>
            );
        },
    },
    {
        accessorKey: 'payment_method',
        meta: { label: 'Método de pago' },
        header: 'Método de pago',
        cell: ({ row }) =>
            paymentMethodLabels[row.original.payment_method] ??
            row.original.payment_method,
    },
    {
        id: 'actions',
        cell: ({ row }) => {
            const service = row.original;
            return (
                <Can permission={Permission.DELETE_SERVICES}>
                    <DataTableRowActions
                        editUrl={services.edit(service.id).url}
                        onDelete={() =>
                            router.delete(services.destroy(service.id).url, {
                                preserveScroll: true,
                            })
                        }
                    />
                </Can>
            );
        },
    },
];
