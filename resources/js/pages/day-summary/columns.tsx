import { Badge } from '@/components/ui/badge';

import type { ColumnDef } from '@tanstack/react-table';
import type { Service } from '@/types';

function addMinutes(time: string, minutes: number): string {
    const [h, m] = time.split(':').map(Number);
    const total = h * 60 + m + minutes;
    const hh = Math.floor(total / 60) % 24;
    const mm = total % 60;
    return `${String(hh).padStart(2, '0')}:${String(mm).padStart(2, '0')}`;
}

function formatTime(time: string): string {
    return time.slice(0, 5);
}

function thirdPartyName(
    tp:
        | {
              company_name: string | null;
              first_name: string | null;
              first_lastname: string | null;
              is_natural_person: boolean;
          }
        | undefined,
): string {
    if (!tp) return '—';
    if (!tp.is_natural_person && tp.company_name) return tp.company_name;
    return [tp.first_name, tp.first_lastname].filter(Boolean).join(' ') || '—';
}

export const columns: ColumnDef<Service, unknown>[] = [
    {
        id: 'plate',
        meta: { label: 'Placa' },
        header: 'Placa',
        cell: ({ row }) => {
            const vehicle = row.original.vehicle;
            return (
                <div className="flex items-center gap-1.5">
                    <span className="font-medium">{vehicle?.plate ?? '—'}</span>
                    {vehicle?.is_third_party && (
                        <Badge
                            variant="outline"
                            className="text-xs text-blue-600 dark:text-blue-400"
                        >
                            3ro
                        </Badge>
                    )}
                </div>
            );
        },
    },
    {
        id: 'driver_provider',
        meta: { label: 'Conductor/Proveedor' },
        header: 'Conductor/Proveedor',
        cell: ({ row }) => {
            const { vehicle, driver } = row.original;
            if (vehicle?.is_third_party) {
                return (
                    <div className="flex flex-col">
                        <span>{thirdPartyName(vehicle.third_party)}</span>
                        <span className="text-xs text-muted-foreground">
                            Proveedor
                        </span>
                    </div>
                );
            }
            if (driver) {
                return `${driver.first_name} ${driver.first_lastname}`;
            }
            return <span className="text-muted-foreground">—</span>;
        },
    },
    {
        id: 'schedule',
        meta: { label: 'Horario' },
        header: 'Horario',
        cell: ({ row }) => {
            const s = row.original;
            const plannedEnd = addMinutes(
                s.planned_start_time,
                s.planned_duration,
            );
            return (
                <div className="flex flex-col">
                    <span>
                        {formatTime(s.planned_start_time)} - {plannedEnd}
                    </span>
                    {s.actual_start_time && s.actual_end_time && (
                        <span className="text-xs text-muted-foreground">
                            Real: {formatTime(s.actual_start_time)} -{' '}
                            {formatTime(s.actual_end_time)}
                        </span>
                    )}
                </div>
            );
        },
    },
    {
        id: 'client',
        meta: { label: 'Cliente' },
        header: 'Cliente',
        cell: ({ row }) => {
            const contract = row.original.contract;
            return (
                <div className="flex flex-col">
                    <span>{thirdPartyName(contract?.third_party)}</span>
                    {contract?.contract_number && (
                        <span className="text-xs text-muted-foreground">
                            {contract.contract_number}
                        </span>
                    )}
                </div>
            );
        },
    },
    {
        id: 'status',
        meta: { label: 'Estado' },
        header: 'Estado',
        cell: ({ row }) => {
            const status = row.original.service_status;
            return (
                <Badge
                    className={
                        status === 'closed'
                            ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300'
                            : 'bg-orange-100 text-orange-700 dark:bg-orange-900 dark:text-orange-300'
                    }
                >
                    {status === 'closed' ? 'Cerrado' : 'Abierto'}
                </Badge>
            );
        },
    },
    {
        id: 'incidents',
        meta: { label: 'Novedades' },
        header: 'Novedades',
        cell: ({ row }) => {
            const count = row.original.service_incidents_count ?? 0;
            if (count > 0) {
                return (
                    <Badge
                        variant="outline"
                        className="border-yellow-400 text-yellow-600 dark:text-yellow-400"
                    >
                        {count} Nov
                    </Badge>
                );
            }
            return <span className="text-muted-foreground">—</span>;
        },
    },
];
