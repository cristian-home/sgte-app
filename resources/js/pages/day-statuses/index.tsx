import { Head, router } from '@inertiajs/react';
import {
    calendar,
    calendarMonth,
} from '@/actions/App/Http/Controllers/DayStatusController';
import AnnualCalendar from '@/components/day-statuses/annual-calendar';
import DayServicesTable from '@/components/day-statuses/day-services-table';
import MonthDetail from '@/components/day-statuses/month-detail';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { DayServiceEntry } from '@/components/day-statuses/day-services-table';
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
    month: number | null;
    selectedDate: string | null;
    dayServices: DayServiceEntry[] | null;
}

const LEGEND_ITEMS = [
    { label: 'Sin servicios', className: 'bg-neutral-800 dark:bg-neutral-700' },
    { label: 'Proyectado', className: 'bg-orange-500' },
    { label: 'Ejecutado', className: 'bg-green-500' },
] as const;

export default function DayStatusesIndex({
    dayStatuses,
    serviceCounts,
    year,
    month,
    selectedDate,
    dayServices,
}: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Calendario',
            href: calendar(year).url,
        },
    ];

    function handleYearChange(newYear: number) {
        router.get(calendar(newYear).url);
    }

    function handleMonthClick(monthIndex: number) {
        router.get(calendarMonth({ year, month: monthIndex + 1 }).url);
    }

    function handleDayClick(dateKey: string) {
        const day = parseInt(dateKey.split('-')[2], 10);
        router.get(
            calendarMonth({ year, month: month! }).url,
            { selectedDay: day },
            { preserveState: true },
        );
    }

    function handlePrevMonth() {
        if (month === 1) {
            router.get(calendarMonth({ year: year - 1, month: 12 }).url);
        } else {
            router.get(calendarMonth({ year, month: month! - 1 }).url);
        }
    }

    function handleNextMonth() {
        if (month === 12) {
            router.get(calendarMonth({ year: year + 1, month: 1 }).url);
        } else {
            router.get(calendarMonth({ year, month: month! + 1 }).url);
        }
    }

    function handleBackToYear() {
        router.get(calendar(year).url);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Calendario" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="relative flex-1 overflow-hidden rounded-xl border bg-background p-6">
                    {month === null ? (
                        <AnnualCalendar
                            year={year}
                            dayStatuses={dayStatuses}
                            serviceCounts={serviceCounts}
                            onMonthClick={handleMonthClick}
                            onYearChange={handleYearChange}
                        />
                    ) : (
                        <div className="flex flex-col gap-4">
                            <MonthDetail
                                year={year}
                                month={month - 1}
                                dayStatuses={dayStatuses}
                                serviceCounts={serviceCounts}
                                onDayClick={handleDayClick}
                                onPrevMonth={handlePrevMonth}
                                onNextMonth={handleNextMonth}
                                onBackToYear={handleBackToYear}
                                selectedDate={selectedDate}
                            />
                            {selectedDate && dayServices && (
                                <DayServicesTable
                                    date={selectedDate}
                                    services={dayServices}
                                />
                            )}
                        </div>
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
