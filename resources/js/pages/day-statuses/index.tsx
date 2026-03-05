import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { index as dayStatusesIndex } from '@/actions/App/Http/Controllers/DayStatusController';
import { index as servicesIndex } from '@/actions/App/Http/Controllers/ServiceController';
import AnnualCalendar from '@/components/day-statuses/annual-calendar';
import MonthDetail from '@/components/day-statuses/month-detail';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

export interface DayStatusEntry {
    id: number;
    date: string;
    status: string;
    executor_id: number | null;
    executed_at: string | null;
    executor?: { id: number; name: string } | null;
}

export interface ServiceCountEntry {
    service_date: string;
    total: number;
    open_count: number;
}

interface Props {
    dayStatuses: Record<string, DayStatusEntry>;
    serviceCounts: Record<string, ServiceCountEntry>;
    year: number;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Calendario',
        href: dayStatusesIndex().url,
    },
];

const LEGEND_ITEMS = [
    { label: 'Sin servicios', className: 'bg-neutral-800 dark:bg-neutral-700' },
    { label: 'Proyectado', className: 'bg-orange-500' },
    { label: 'Ejecutado', className: 'bg-green-500' },
] as const;

export default function DayStatusesIndex({
    dayStatuses,
    serviceCounts,
    year,
}: Props) {
    const [selectedMonth, setSelectedMonth] = useState<number | null>(null);

    function handleYearChange(newYear: number) {
        setSelectedMonth(null);
        router.get(
            dayStatusesIndex().url,
            { year: newYear },
            { preserveState: true },
        );
    }

    function handleDayClick(dateKey: string) {
        router.get(servicesIndex().url, { 'filter[service_date]': dateKey });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Calendario" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="relative flex-1 overflow-hidden rounded-xl border bg-background p-6">
                    {selectedMonth === null ? (
                        <AnnualCalendar
                            year={year}
                            dayStatuses={dayStatuses}
                            serviceCounts={serviceCounts}
                            onMonthClick={setSelectedMonth}
                            onYearChange={handleYearChange}
                        />
                    ) : (
                        <MonthDetail
                            year={year}
                            month={selectedMonth}
                            dayStatuses={dayStatuses}
                            serviceCounts={serviceCounts}
                            onDayClick={handleDayClick}
                            onClose={() => setSelectedMonth(null)}
                        />
                    )}

                    <div className="mt-6 flex flex-wrap items-center gap-4">
                        {LEGEND_ITEMS.map(({ label, className }) => (
                            <div
                                key={label}
                                className="flex items-center gap-1.5"
                            >
                                <div
                                    className={cn(
                                        'size-3 rounded-sm',
                                        className,
                                    )}
                                />
                                <span className="text-xs text-muted-foreground">
                                    {label}
                                </span>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
