import { Link, router } from '@inertiajs/react';
import { Can } from '@/components/can';
import {
    DataTableColumnHeader,
    DataTableRowActions,
} from '@/components/data-table';
import { type EditableDriver } from '@/components/drivers/driver-dialog';
import { DriverLicensePill } from '@/components/drivers/driver-license-pill';
import { Badge } from '@/components/ui/badge';
import { Permission } from '@/enums/Permission';
import drivers from '@/routes/drivers';

import type { ColumnDef, Table } from '@tanstack/react-table';
import type { Driver } from '@/types/models';

// Driver as it arrives from DriverController@index — the controller
// eager-loads the document_type relation and serializes it as null
// when missing, while the global Driver type uses the
// `relation?: T` (undefined-only) shape.
type DriverRow = Driver & {
    document_type?: { id: number; code: string; name: string } | null;
};

export interface DriverTableMeta {
    onEdit: (driver: EditableDriver) => void;
}

function meta(table: Table<DriverRow>): DriverTableMeta {
    return table.options.meta as DriverTableMeta;
}

function fullName(driver: DriverRow): string {
    return [driver.first_name, driver.first_lastname]
        .filter(Boolean)
        .join(' ')
        .trim();
}

function documentLabel(driver: DriverRow): string {
    const code = driver.document_type?.code ?? '';
    const number = driver.identification_number ?? '';
    return `${code} ${number}`.trim();
}

export const columns: ColumnDef<DriverRow, unknown>[] = [
    {
        id: 'documento',
        meta: { label: 'Documento' },
        header: 'Documento',
        cell: ({ row }) => (
            <Link
                href={drivers.show(row.original.id).url}
                className="font-mono text-primary hover:underline"
            >
                {documentLabel(row.original)}
            </Link>
        ),
    },
    {
        id: 'nombre',
        meta: { label: 'Nombre' },
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="Nombre" />
        ),
        accessorFn: (row) => fullName(row),
        cell: ({ row }) => (
            <span className="font-medium">{fullName(row.original)}</span>
        ),
    },
    {
        accessorKey: 'license_category',
        meta: { label: 'Categoría' },
        header: 'Categoría',
        cell: ({ row }) => (
            <span className="font-mono text-sm">
                {row.original.license_category}
            </span>
        ),
    },
    {
        id: 'licencia',
        meta: { label: 'Licencia' },
        header: 'Licencia',
        cell: ({ row }) => <DriverLicensePill driver={row.original} />,
    },
    {
        id: 'seg_social',
        meta: { label: 'Seg. Social' },
        header: 'Seg. Social',
        cell: ({ row }) => (
            <Badge
                variant={
                    row.original.has_social_security ? 'default' : 'outline'
                }
            >
                {row.original.has_social_security ? 'Sí' : 'No'}
            </Badge>
        ),
    },
    {
        id: 'vinculacion',
        meta: { label: 'Vinculación' },
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="Vinculación" />
        ),
        accessorKey: 'active',
        cell: ({ row }) => (
            <Badge variant={row.original.active ? 'default' : 'outline'}>
                {row.original.active ? 'Activo' : 'Inactivo'}
            </Badge>
        ),
    },
    {
        id: 'actions',
        cell: ({ row, table }) => {
            const driver = row.original;
            return (
                <Can permission={Permission.DELETE_DRIVERS}>
                    <DataTableRowActions
                        onEdit={() => meta(table).onEdit(driver)}
                        onDelete={() =>
                            router.delete(drivers.destroy(driver.id).url, {
                                preserveScroll: true,
                            })
                        }
                    />
                </Can>
            );
        },
    },
];
