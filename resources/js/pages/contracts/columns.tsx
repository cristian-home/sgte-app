import { Link, router } from '@inertiajs/react';
import { Can } from '@/components/can';
import { ContractPeriodPill } from '@/components/contracts/contract-period-pill';
import {
    DataTableColumnHeader,
    DataTableRowActions,
} from '@/components/data-table';
import { Permission } from '@/enums/Permission';
import { dateFormatter, parseDueDate } from '@/lib/document-status';
import contracts from '@/routes/contracts';
import thirdParties from '@/routes/third-parties';

import type { ColumnDef } from '@tanstack/react-table';
import type { Contract, DocumentType, ThirdParty } from '@/types/models';

// Contract as it arrives from ContractController@index — the controller
// eager-loads `thirdParty.documentType` and may serialize as null, while
// the global Contract type uses `relation?: T` (undefined-only). Keeping
// both compatible via `Pick + & relations` matches the drivers/vehicles
// columns pattern.
export type ContractRow = Contract & {
    third_party?:
        | (ThirdParty & {
              document_type?: Pick<DocumentType, 'id' | 'code' | 'name'> | null;
          })
        | null;
};

const CONTRACT_OBJECT_LABELS: Record<string, string> = {
    business: 'Empresarial',
    tourism: 'Turismo',
    health: 'Salud',
    occasional: 'Ocasional',
};

function customerName(contract: ContractRow): string {
    const tp = contract.third_party;
    if (!tp) return '—';
    if (tp.is_natural_person) {
        return (
            [tp.first_name, tp.first_lastname]
                .filter(Boolean)
                .join(' ')
                .trim() || '—'
        );
    }
    return tp.company_name ?? '—';
}

function formatDate(date: string | null): string {
    const parsed = parseDueDate(date);
    if (parsed === null) {
        return '—';
    }
    return dateFormatter.format(parsed);
}

export const columns: ColumnDef<ContractRow, unknown>[] = [
    {
        accessorKey: 'contract_number',
        meta: { label: 'Número' },
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="Número" />
        ),
        cell: ({ row }) => (
            <Link
                href={contracts.show(row.original.id).url}
                className="font-mono text-primary hover:underline"
            >
                {row.original.contract_number}
            </Link>
        ),
    },
    {
        id: 'cliente',
        meta: { label: 'Cliente' },
        header: 'Cliente',
        cell: ({ row }) => {
            const tp = row.original.third_party;
            if (!tp) {
                return <span className="text-muted-foreground">—</span>;
            }
            return (
                <Link
                    href={thirdParties.show(tp.id).url}
                    className="font-medium text-primary hover:underline"
                >
                    {customerName(row.original)}
                </Link>
            );
        },
    },
    {
        accessorKey: 'contract_object',
        meta: { label: 'Objeto' },
        header: 'Objeto',
        cell: ({ row }) => (
            <span>
                {CONTRACT_OBJECT_LABELS[row.original.contract_object] ??
                    row.original.contract_object}
            </span>
        ),
    },
    {
        id: 'vigencia',
        meta: { label: 'Vigencia' },
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="Vigencia" />
        ),
        cell: ({ row }) => (
            <span className="text-sm whitespace-nowrap">
                {formatDate(row.original.start_date)}
                {' → '}
                {formatDate(row.original.end_date)}
            </span>
        ),
    },
    {
        id: 'estado',
        meta: { label: 'Estado' },
        header: 'Estado',
        cell: ({ row }) => <ContractPeriodPill contract={row.original} />,
    },
    {
        id: 'actions',
        cell: ({ row }) => {
            const contract = row.original;
            return (
                <Can permission={Permission.DELETE_CONTRACTS}>
                    <DataTableRowActions
                        editUrl={contracts.edit(contract.id).url}
                        onDelete={() =>
                            router.delete(contracts.destroy(contract.id).url, {
                                preserveScroll: true,
                            })
                        }
                    />
                </Can>
            );
        },
    },
];
