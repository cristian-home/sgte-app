import { Head, usePage } from '@inertiajs/react';
import { useCallback, useMemo, useRef, useState } from 'react';
import { index as ganttIndex } from '@/actions/App/Http/Controllers/GanttController';
import { type MunicipalityOption } from '@/components/municipality-combobox';
import AppLayout from '@/layouts/app-layout';
import GanttHeader from './components/gantt-header';
import HourlyGrid, {
    defaultEpochFor,
    defaultNumDays,
} from './components/hourly-grid';
import { type Ymd } from './utils/coordinates';

import type { BreadcrumbItem } from '@/types';
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
    const sharedConfig = usePage().props.config as
        | { operation_tz?: string }
        | undefined;
    const operationTz = sharedConfig?.operation_tz ?? 'America/Bogota';

    // SSR date becomes the initial center of the timeline. The epoch is
    // 182 days earlier (yields a 365-day window centered on it).
    const epoch = useMemo<Ymd>(() => defaultEpochFor(date), [date]);
    const numDays = useMemo(() => defaultNumDays(), []);

    const initialDay = useMemo(
        () => ({ date: date as Ymd, services }),
        [date, services],
    );

    // `centerDate` mirrors what the timeline reports as "currently
    // centered". The page uses it to keep the header date picker and
    // URL in sync. SSR provides the initial value.
    const [centerDate, setCenterDate] = useState<Ymd>(date as Ymd);

    const jumpToDateRef = useRef<((date: Ymd) => void) | null>(null);

    const handleCenterDateChange = useCallback(
        (newDate: Ymd) => {
            setCenterDate(newDate);
            // Update URL without an Inertia navigate so we don't trigger
            // a full SSR reload during scroll. F5 / back-forward still
            // work because the URL is canonical.
            const url = new URL(window.location.href);
            url.searchParams.set('date', newDate);
            window.history.replaceState(window.history.state, '', url.toString());
        },
        [],
    );

    const handleJumpToDate = useCallback((newDate: string) => {
        jumpToDateRef.current?.(newDate as Ymd);
    }, []);

    const isExecuted = dayStatus?.status === 'executed';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Planificador Gantt" />
            <div
                className="flex flex-col gap-4 p-4"
                // Same height-cap pattern as /driver — see
                // [[project-app-layout-height-chain]] memory.
                style={{ height: 'calc(100svh - 5rem)' }}
            >
                <GanttHeader
                    date={centerDate}
                    municipalityId={municipalityId}
                    municipalities={municipalities}
                    dayStatus={dayStatus}
                    canCreateServices={canCreateServices}
                    onJumpToDate={handleJumpToDate}
                />
                <div className="min-h-0 flex-1 overflow-hidden rounded-lg border">
                    <HourlyGrid
                        vehicles={vehicles}
                        initialDay={initialDay}
                        epoch={epoch}
                        operationTz={operationTz}
                        canCreateServices={canCreateServices}
                        isExecuted={isExecuted}
                        numDays={numDays}
                        onCenterDateChange={handleCenterDateChange}
                        onMount={(jump) => {
                            jumpToDateRef.current = jump;
                        }}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
