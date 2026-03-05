import { Head } from '@inertiajs/react';
import { useMemo } from 'react';
import { index as ganttIndex } from '@/actions/App/Http/Controllers/GanttController';
import { type MunicipalityOption } from '@/components/municipality-combobox';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import type { DayStatus, Service, Vehicle } from '@/types/models';
import GanttHeader from './components/gantt-header';
import HourlyGrid from './components/hourly-grid';

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
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <GanttHeader
                    date={date}
                    municipalityId={municipalityId}
                    municipalities={municipalities}
                    dayStatus={dayStatus}
                    canCreateServices={canCreateServices}
                />
                <div className="overflow-x-auto rounded-lg border">
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
