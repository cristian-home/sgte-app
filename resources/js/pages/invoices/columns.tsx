import { Link, router } from '@inertiajs/react';
import {
    DataTableColumnHeader,
    DataTableRowActions,
} from '@/components/data-table';
import { type EditableInvoice } from '@/components/invoices/invoice-dialog';
import { PaymentStatusPill } from '@/components/invoices/payment-status-pill';
import { Permission } from '@/enums/Permission';
import { usePermissions } from '@/hooks/use-permissions';
import { dateFormatter, parseDueDate } from '@/lib/document-status';
import invoices from '@/routes/invoices';
import thirdParties from '@/routes/third-parties';

import type { ColumnDef, Table } from '@tanstack/react-table';
import type { DocumentType, Invoice, ThirdParty } from '@/types/models';

// Invoice as it arrives from InvoiceController@index — eager-loads
// `thirdParty.documentType` and `withCount('services')`; global Invoice
// type uses `relation?: T` (undefined-only) while the payload returns
// `relation: T | null`. Pick + & relations keeps both compatible
// (matches drivers/vehicles/contracts).
export type InvoiceRow = Invoice & {
    services_count?: number;
    third_party?:
        | (ThirdParty & {
              document_type?: Pick<DocumentType, 'id' | 'code' | 'name'> | null;
          })
        | null;
};

export interface InvoiceTableMeta {
    onEdit: (invoice: EditableInvoice) => void;
}

function meta(table: Table<InvoiceRow>): InvoiceTableMeta {
    return table.options.meta as InvoiceTableMeta;
}

const currencyFormatter = new Intl.NumberFormat('es-CO', {
    style: 'currency',
    currency: 'COP',
    maximumFractionDigits: 0,
});

function customerName(invoice: InvoiceRow): string {
    const tp = invoice.third_party;
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

export const columns: ColumnDef<InvoiceRow, unknown>[] = [
    {
        accessorKey: 'invoice_number',
        meta: { label: 'Número' },
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="Número" />
        ),
        cell: ({ row }) => (
            <Link
                href={invoices.show(row.original.id).url}
                className="font-mono text-primary hover:underline"
            >
                {row.original.invoice_number}
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
        accessorKey: 'issue_date',
        meta: { label: 'Fecha Emisión' },
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="Fecha Emisión" />
        ),
        cell: ({ row }) => (
            <span className="text-sm whitespace-nowrap">
                {formatDate(row.original.issue_date)}
            </span>
        ),
    },
    {
        accessorKey: 'total_value',
        meta: { label: 'Valor Total' },
        header: ({ column }) => (
            <div className="text-right">
                <DataTableColumnHeader column={column} title="Valor Total" />
            </div>
        ),
        cell: ({ row }) => (
            <div className="text-right font-medium tabular-nums">
                {currencyFormatter.format(Number(row.original.total_value))}
            </div>
        ),
    },
    {
        id: 'estado',
        meta: { label: 'Estado' },
        header: 'Estado',
        cell: ({ row }) => <PaymentStatusPill invoice={row.original} />,
    },
    {
        id: 'actions',
        cell: ({ row, table }) => (
            <InvoiceRowActions
                invoice={row.original}
                onEdit={meta(table).onEdit}
            />
        ),
    },
];

// Separate component so we can read permissions via the hook — the
// Edit action is available to both admin and accounting (UPDATE_INVOICES),
// but the Delete action is admin-only (DELETE_INVOICES). Accounting
// must still see the menu with the Edit entry.
function InvoiceRowActions({
    invoice,
    onEdit,
}: {
    invoice: InvoiceRow;
    onEdit: (invoice: EditableInvoice) => void;
}) {
    const { can } = usePermissions();

    const canUpdate = can(Permission.UPDATE_INVOICES);
    const canDelete = can(Permission.DELETE_INVOICES);

    if (!canUpdate && !canDelete) {
        return null;
    }

    return (
        <DataTableRowActions
            onEdit={canUpdate ? () => onEdit(invoice) : undefined}
            onDelete={
                canDelete
                    ? () =>
                          router.delete(invoices.destroy(invoice.id).url, {
                              preserveScroll: true,
                          })
                    : undefined
            }
        />
    );
}
