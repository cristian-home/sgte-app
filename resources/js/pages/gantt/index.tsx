import { Head } from '@inertiajs/react';
import { useMemo } from 'react';
import { index as ganttIndex } from '@/actions/App/Http/Controllers/GanttController';
import { type MunicipalityOption } from '@/components/municipality-combobox';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import GanttHeader from './components/gantt-header';
import HourlyGrid from './components/hourly-grid';
import type { DayStatus, Service, Vehicle } from '@/types/models';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Planificador Gantt', href: ganttIndex().url },
];

interface Props {
    vehicles: Vehicle[];
    services: Service[];
    dayStatus: DayStatus | null;
    municipalities: MunicipalityOption[];
    date: string;
    municipalityId: number | null;
    canCreateServices: boolean;
}

export default function GanttIndex({
    vehicles,
    services,
    dayStatus,
    municipalities,
    date,
    municipalityId,
    canCreateServices,
}: Props) {
    const servicesByVehicle = useMemo(() => {
        const map: Record<number, Service[]> = {};
        for (const s of services) {
            (map[s.vehicle_id] ??= []).push(s);
        }
        return map;
    }, [services]);

    const isExecuted = dayStatus?.status === 'executed';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Planificador Gantt" />
            <div
                className="flex flex-col gap-4 p-4"
                // Cap the Gantt page at the viewport minus the breadcrumb
                // header (h-16 + empirical 1rem of breathing room, same
                // value as the driver page). The inner scroll container
                // then absorbs the remaining height via `flex-1 min-h-0`
                // and the grid scrolls inside the box instead of pushing
                // the document scrollbar.
                style={{ height: 'calc(100svh - 5rem)' }}
            >
                <GanttHeader
                    date={date}
                    municipalityId={municipalityId}
                    municipalities={municipalities}
                    dayStatus={dayStatus}
                    canCreateServices={canCreateServices}
                />
                <div className="min-h-0 flex-1 overflow-auto rounded-lg border">
                    <HourlyGrid
                        vehicles={vehicles}
                        servicesByVehicle={servicesByVehicle}
                        date={date}
                        canCreateServices={canCreateServices}
                        isExecuted={isExecuted}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
