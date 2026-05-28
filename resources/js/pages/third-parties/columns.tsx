import { Link, router } from '@inertiajs/react';
import { Can } from '@/components/can';
import {
    DataTableColumnHeader,
    DataTableRowActions,
} from '@/components/data-table';
import { type EditableThirdParty } from '@/components/third-parties/third-party-dialog';
import { Badge } from '@/components/ui/badge';
import { Permission } from '@/enums/Permission';
import thirdParties from '@/routes/third-parties';

import type { ColumnDef, Table } from '@tanstack/react-table';
import type { DocumentType, Municipality, ThirdParty } from '@/types/models';

// ThirdParty as it arrives from ThirdPartyController@index — the
// controller eager-loads document_type + municipality.department,
// and serializes them as null when absent, while the global
// ThirdParty type uses the `relation?: T` (undefined-only) shape.
type ThirdPartyRow = ThirdParty & {
    document_type?: DocumentType | null;
    municipality?:
        | (Municipality & { department?: { id: number; name: string } })
        | null;
};

export interface ThirdPartyTableMeta {
    onEdit: (thirdParty: EditableThirdParty) => void;
}

function meta(table: Table<ThirdPartyRow>): ThirdPartyTableMeta {
    return table.options.meta as ThirdPartyTableMeta;
}

function nameFor(tp: ThirdPartyRow): string {
    if (tp.is_natural_person) {
        return (
            `${tp.first_name ?? ''} ${tp.first_lastname ?? ''}`.trim() || '—'
        );
    }
    return tp.company_name ?? '—';
}

function documentLabel(tp: ThirdPartyRow): string {
    const code = tp.document_type?.code ?? '';
    const number = tp.identification_number ?? '';
    return `${code} ${number}`.trim();
}

export const columns: ColumnDef<ThirdPartyRow, unknown>[] = [
    {
        id: 'documento',
        meta: { label: 'Documento' },
        header: 'Documento',
        cell: ({ row }) => (
            <Link
                href={thirdParties.show(row.original.id).url}
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
        accessorFn: (row) => nameFor(row),
        cell: ({ row }) => (
            <span className="font-medium">{nameFor(row.original)}</span>
        ),
    },
    {
        id: 'tipo',
        meta: { label: 'Tipo' },
        header: 'Tipo',
        cell: ({ row }) =>
            row.original.is_natural_person ? 'Natural' : 'Jurídica',
    },
    {
        id: 'roles',
        meta: { label: 'Roles' },
        header: 'Roles',
        cell: ({ row }) => {
            const tp = row.original;
            if (!tp.is_customer && !tp.is_provider) {
                return <span className="text-muted-foreground">—</span>;
            }
            return (
                <div className="flex flex-wrap gap-1">
                    {tp.is_customer && <Badge variant="default">Cliente</Badge>}
                    {tp.is_provider && (
                        <Badge variant="secondary">Proveedor</Badge>
                    )}
                </div>
            );
        },
    },
    {
        id: 'municipio',
        meta: { label: 'Municipio' },
        header: 'Municipio',
        cell: ({ row }) => (
            <span className="capitalize">
                {row.original.municipality?.name ?? '—'}
            </span>
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
            const tp = row.original;
            return (
                <Can permission={Permission.DELETE_THIRD_PARTIES}>
                    <DataTableRowActions
                        onEdit={() => meta(table).onEdit(tp)}
                        onDelete={() =>
                            router.delete(thirdParties.destroy(tp.id).url, {
                                preserveScroll: true,
                            })
                        }
                    />
                </Can>
            );
        },
    },
];
