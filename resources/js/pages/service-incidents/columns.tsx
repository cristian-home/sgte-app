import { Link, router } from '@inertiajs/react';
import {
    DataTableColumnHeader,
    DataTableRowActions,
} from '@/components/data-table';
import {
    IncidentSeverityPill,
    incidentSeverityRowTint,
} from '@/components/incidents/incident-severity-pill';
import { Badge } from '@/components/ui/badge';
import { Permission } from '@/enums/Permission';
import { usePermissions } from '@/hooks/use-permissions';
import { dateFormatter, parseDueDate } from '@/lib/document-status';
import serviceIncidents from '@/routes/service-incidents';
import services from '@/routes/services';

import type { ColumnDef } from '@tanstack/react-table';
import type {
    Contract,
    IncidentType,
    Service,
    ServiceIncident,
    Vehicle,
} from '@/types/models';

// Incident as it arrives from ServiceIncidentController@index —
// the controller eager-loads service.vehicle + service.contract +
// incidentType + registrar. The global types use `relation?: T`
// (undefined-only) while Eloquent can serialize as null. Pick + &
// relations keeps both compatible (matches invoices-crud).
export type ServiceIncidentRow = ServiceIncident & {
    service?:
        | (Pick<
              Service,
              'id' | 'service_date' | 'vehicle_id' | 'contract_id'
          > & {
              vehicle?: Pick<Vehicle, 'id' | 'plate'> | null;
              contract?: Pick<Contract, 'id' | 'contract_number'> | null;
          })
        | null;
    incident_type?: Pick<
        IncidentType,
        'id' | 'code' | 'name' | 'severity'
    > | null;
    registrar?: { id: number; name: string } | null;
};

const reportedAtFormatter = new Intl.DateTimeFormat('es-CO', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

function formatServiceDate(date: string | null | undefined): string {
    const parsed = parseDueDate(date ?? null);
    if (parsed === null) {
        return '—';
    }
    return dateFormatter.format(parsed);
}

function formatReportedAt(reportedAt: string | null | undefined): string {
    if (!reportedAt) return '—';
    // Incident reported_at is sent as epoch-seconds by the cast.
    const ms = Number(reportedAt) * 1000;
    if (Number.isNaN(ms) || ms <= 0) {
        const fallback = new Date(reportedAt);
        if (Number.isNaN(fallback.getTime())) {
            return '—';
        }
        return reportedAtFormatter.format(fallback);
    }
    return reportedAtFormatter.format(new Date(ms));
}

export const columns: ColumnDef<ServiceIncidentRow, unknown>[] = [
    {
        id: 'servicio',
        meta: { label: 'Servicio' },
        header: 'Servicio',
        cell: ({ row }) => {
            const service = row.original.service;
            if (!service) {
                return <span className="text-muted-foreground">—</span>;
            }
            return (
                <Link
                    href={services.show(service.id).url}
                    className="text-primary hover:underline"
                >
                    <span className="font-mono">
                        {service.vehicle?.plate ?? `#${service.id}`}
                    </span>
                    <span className="ml-1 text-xs text-muted-foreground">
                        {formatServiceDate(service.service_date)}
                    </span>
                </Link>
            );
        },
    },
    {
        id: 'tipo',
        meta: { label: 'Tipo' },
        header: 'Tipo',
        cell: ({ row }) => {
            const type = row.original.incident_type;
            return (
                <div className="flex items-center gap-2">
                    <span>{type?.name ?? '—'}</span>
                    <IncidentSeverityPill severity={type?.severity ?? null} />
                </div>
            );
        },
    },
    {
        accessorKey: 'description',
        meta: { label: 'Descripción' },
        header: 'Descripción',
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
        meta: { label: 'Reporte' },
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="Reporte" />
        ),
        cell: ({ row }) => (
            <span className="text-sm whitespace-nowrap">
                {formatReportedAt(row.original.reported_at)}
            </span>
        ),
    },
    {
        id: 'registrador',
        meta: { label: 'Registrado Por' },
        header: 'Registrado Por',
        cell: ({ row }) => (
            <div className="flex items-center gap-2">
                <span>{row.original.registrar?.name ?? '—'}</span>
                {row.original.is_driver_report && (
                    <Badge variant="outline">Conductor</Badge>
                )}
            </div>
        ),
    },
    {
        id: 'impacto',
        meta: { label: 'Impacto' },
        header: 'Impacto',
        cell: ({ row }) =>
            row.original.affects_billing ? (
                <Badge variant="destructive">Afecta facturación</Badge>
            ) : (
                <span className="text-muted-foreground">—</span>
            ),
    },
    {
        id: 'actions',
        cell: ({ row }) => <IncidentRowActions incident={row.original} />,
    },
];

export { incidentSeverityRowTint };

// Separate component so we can read permissions via the hook — Edit is
// available to admin (UPDATE_INCIDENTS); operator and accounting lack
// UPDATE/DELETE so their rows show no menu entries.
function IncidentRowActions({ incident }: { incident: ServiceIncidentRow }) {
    const { can } = usePermissions();

    const canUpdate = can(Permission.UPDATE_INCIDENTS);
    const canDelete = can(Permission.DELETE_INCIDENTS);

    if (!canUpdate && !canDelete) {
        return null;
    }

    return (
        <DataTableRowActions
            editUrl={
                canUpdate ? serviceIncidents.edit(incident.id).url : undefined
            }
            onDelete={
                canDelete
                    ? () =>
                          router.delete(
                              serviceIncidents.destroy(incident.id).url,
                              {
                                  preserveScroll: true,
                              },
                          )
                    : undefined
            }
        />
    );
}
