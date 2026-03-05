import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useMemo } from 'react';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { DayStatusEnum, DayStatusEnumLabel } from '@/enums/DayStatusEnum';
import {
    MONTH_NAMES_ES,
    WEEKDAY_NAMES_ES,
    getWeeksOfMonth,
} from '@/lib/date-utils';
import { cn } from '@/lib/utils';
import type {
    DayStatusEntry,
    ServiceCountEntry,
} from '@/pages/day-statuses/index';

interface AnnualCalendarProps {
    year: number;
    dayStatuses: Record<string, DayStatusEntry>;
    serviceCounts: Record<string, ServiceCountEntry>;
    onMonthClick: (month: number) => void;
    onYearChange: (year: number) => void;
}

function getDayColorClass(status: string | undefined): string {
    if (!status) return 'bg-neutral-800 dark:bg-neutral-700';
    if (status === DayStatusEnum.Projected) return 'bg-orange-500';
    if (status === DayStatusEnum.Executed) return 'bg-green-500';
    return 'bg-neutral-800 dark:bg-neutral-700';
}

export default function AnnualCalendar({
    year,
    dayStatuses,
    serviceCounts,
    onMonthClick,
    onYearChange,
}: AnnualCalendarProps) {
    const months = useMemo(() => {
        return Array.from({ length: 12 }, (_, month) => {
            const weeks = getWeeksOfMonth(year, month);
            let monthServiceTotal = 0;

            weeks.forEach((week) =>
                week.forEach((day) => {
                    if (day.isCurrentMonth && serviceCounts[day.dateKey]) {
                        monthServiceTotal += Number(
                            serviceCounts[day.dateKey].total,
                        );
                    }
                }),
            );

            return { month, weeks, monthServiceTotal };
        });
    }, [year, serviceCounts]);

    return (
        <div>
            <div className="mb-6 flex items-center justify-center gap-4">
                <Button
                    variant="ghost"
                    size="icon"
                    onClick={() => onYearChange(year - 1)}
                >
                    <ChevronLeft className="size-5" />
                </Button>
                <h2 className="text-2xl font-bold tabular-nums">{year}</h2>
                <Button
                    variant="ghost"
                    size="icon"
                    onClick={() => onYearChange(year + 1)}
                >
                    <ChevronRight className="size-5" />
                </Button>
            </div>

            <div className="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
                {months.map(({ month, weeks, monthServiceTotal }) => (
                    <button
                        key={month}
                        type="button"
                        onClick={() => onMonthClick(month)}
                        className="rounded-xl border p-3 text-left transition-colors hover:bg-accent"
                    >
                        <div className="mb-1 flex items-baseline justify-between">
                            <span className="text-sm font-semibold">
                                {MONTH_NAMES_ES[month]}
                            </span>
                            {monthServiceTotal > 0 && (
                                <span className="text-xs text-muted-foreground">
                                    {monthServiceTotal} serv.
                                </span>
                            )}
                        </div>

                        <div className="grid grid-cols-7 gap-px">
                            {WEEKDAY_NAMES_ES.map((d) => (
                                <div
                                    key={d}
                                    className="text-center text-[9px] text-muted-foreground"
                                >
                                    {d.charAt(0)}
                                </div>
                            ))}

                            {weeks.flatMap((week) =>
                                week.map((day) => {
                                    if (!day.isCurrentMonth) {
                                        return (
                                            <div
                                                key={day.dateKey}
                                                className="size-4"
                                                aria-hidden
                                            />
                                        );
                                    }

                                    const ds = dayStatuses[day.dateKey];
                                    const sc = serviceCounts[day.dateKey];
                                    const colorClass = getDayColorClass(
                                        ds?.status,
                                    );

                                    const tooltipLines = [day.dateKey];
                                    if (ds)
                                        tooltipLines.push(
                                            DayStatusEnumLabel[
                                                ds.status as DayStatusEnum
                                            ] ?? ds.status,
                                        );
                                    if (sc)
                                        tooltipLines.push(
                                            `${sc.total} servicios`,
                                        );

                                    return (
                                        <Tooltip key={day.dateKey}>
                                            <TooltipTrigger asChild>
                                                <div
                                                    className={cn(
                                                        'size-4 rounded-sm',
                                                        colorClass,
                                                        day.isToday &&
                                                            'ring-2 ring-primary',
                                                    )}
                                                />
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                {tooltipLines.map((line) => (
                                                    <div key={line}>{line}</div>
                                                ))}
                                            </TooltipContent>
                                        </Tooltip>
                                    );
                                }),
                            )}
                        </div>
                    </button>
                ))}
            </div>
        </div>
    );
}
