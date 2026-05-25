import { Link } from '@inertiajs/react';
import { Eye } from 'lucide-react';
import { DataTableColumnHeader } from '@/components/data-table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatTimestampInViewerTz } from '@/lib/datetime';
import {
    subjectTypeLabel,
    SUBJECT_TYPE_LINK_MAP,
    type ActivityRow,
    type SubjectTypeOption,
} from '@/types/audit-log';

import type { ColumnDef } from '@tanstack/react-table';

/**
 * Meta payload the columns read off `table.options.meta`. The index
 * page wires the `onSelect` callback + the backend-supplied
 * `subjectTypes` list so the Entidad cell can resolve the Spanish
 * label without a second lookup.
 */
export interface AuditLogTableMeta {
    subjectTypes: SubjectTypeOption[];
    onSelect: (activity: ActivityRow) => void;
}

function formatTimestamp(iso: string | null): string {
    return formatTimestampInViewerTz(iso) || '—';
}

function isReservedPropertyKey(key: string): boolean {
    return (
        key === 'attributes' ||
        key === 'old' ||
        key === 'edited_on_executed_day' ||
        key === 'justification'
    );
}

export const auditLogColumns: ColumnDef<ActivityRow, unknown>[] = [
    {
        accessorKey: 'created_at',
        meta: { label: 'Fecha' },
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="Fecha" />
        ),
        cell: ({ row }) => (
            <span className="font-mono text-xs whitespace-nowrap">
                {formatTimestamp(row.original.created_at)}
            </span>
        ),
    },
    {
        id: 'usuario',
        meta: { label: 'Usuario' },
        header: 'Usuario',
        cell: ({ row }) => {
            const causer = row.original.causer;
            if (!causer) {
                return <span className="text-muted-foreground">Sistema</span>;
            }
            return (
                <div className="flex min-w-0 flex-col">
                    <span className="truncate font-medium">{causer.name}</span>
                    <span className="truncate text-xs text-muted-foreground">
                        {causer.email}
                    </span>
                </div>
            );
        },
    },
    {
        accessorKey: 'event',
        meta: { label: 'Acción' },
        header: 'Acción',
        cell: ({ row }) =>
            row.original.event ? (
                <Badge variant="outline">{row.original.event}</Badge>
            ) : (
                <span className="text-muted-foreground">—</span>
            ),
    },
    {
        id: 'entidad',
        meta: { label: 'Entidad' },
        header: 'Entidad',
        cell: ({ row, table }) => {
            const meta = table.options.meta as AuditLogTableMeta | undefined;
            const { subject_type, subject_id } = row.original;
            if (!subject_type) {
                return <span className="text-muted-foreground">—</span>;
            }
            const label = subjectTypeLabel(
                subject_type,
                meta?.subjectTypes ?? [],
            );
            const linkBase = SUBJECT_TYPE_LINK_MAP[subject_type];
            const content = (
                <span className="whitespace-nowrap">
                    {label}
                    {subject_id ? ` #${subject_id}` : ''}
                </span>
            );
            if (linkBase && subject_id) {
                return (
                    <Link
                        href={`${linkBase}/${subject_id}`}
                        className="text-primary hover:underline"
                    >
                        {content}
                    </Link>
                );
            }
            return content;
        },
    },
    {
        accessorKey: 'description',
        meta: { label: 'Descripción' },
        header: 'Descripción',
        cell: ({ row }) => (
            <span className="block max-w-md truncate text-sm">
                {row.original.description || '—'}
            </span>
        ),
    },
    {
        id: 'justificacion',
        meta: { label: 'Justificación' },
        header: 'Justificación',
        cell: ({ row }) => {
            const properties = row.original.properties ?? {};
            const justification = properties['justification'];
            if (typeof justification !== 'string' || justification === '') {
                return <span className="text-muted-foreground">—</span>;
            }
            return (
                <span className="block max-w-sm truncate text-sm italic">
                    {justification}
                </span>
            );
        },
    },
    {
        id: 'actions',
        header: () => <span className="sr-only">Acciones</span>,
        cell: ({ row, table }) => {
            const meta = table.options.meta as AuditLogTableMeta | undefined;
            return (
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label="Ver detalles"
                    onClick={() => meta?.onSelect(row.original)}
                >
                    <Eye className="size-4" />
                </Button>
            );
        },
    },
];

// Helper exported for the index page's row-tint callback.
export { isReservedPropertyKey };
