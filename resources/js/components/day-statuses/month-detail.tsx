import { format } from 'date-fns';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardAction,
} from '@/components/ui/card';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { DayStatusEnum } from '@/enums/DayStatusEnum';
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

interface MonthDetailProps {
    year: number;
    month: number;
    dayStatuses: Record<string, DayStatusEntry>;
    serviceCounts: Record<string, ServiceCountEntry>;
    onDayClick: (dateKey: string) => void;
    onPrevMonth: () => void;
    onNextMonth: () => void;
    onBackToYear: () => void;
    selectedDate: string | null;
}

function getDayColorClass(status: string | undefined): string {
    if (!status) return 'bg-neutral-100 dark:bg-neutral-800/40';
    if (status === DayStatusEnum.Projected)
        return 'bg-orange-100 dark:bg-orange-500/15';
    if (status === DayStatusEnum.Executed)
        return 'bg-green-100 dark:bg-green-500/15';
    return 'bg-neutral-100 dark:bg-neutral-800/40';
}

function getDayDotClass(status: string | undefined): string {
    if (!status) return 'bg-neutral-800 dark:bg-neutral-600';
    if (status === DayStatusEnum.Projected) return 'bg-orange-500';
    if (status === DayStatusEnum.Executed) return 'bg-green-500';
    return 'bg-neutral-800 dark:bg-neutral-600';
}

export default function MonthDetail({
    year,
    month,
    dayStatuses,
    serviceCounts,
    onDayClick,
    onPrevMonth,
    onNextMonth,
    onBackToYear,
    selectedDate,
}: MonthDetailProps) {
    const weeks = getWeeksOfMonth(year, month);

    return (
        <Card>
            <CardHeader>
                <CardTitle>
                    <button
                        type="button"
                        onClick={onBackToYear}
                        data-dusk="back-to-year"
                        className="transition-colors hover:text-primary"
                    >
                        {MONTH_NAMES_ES[month]} {year}
                    </button>
                </CardTitle>
                <CardAction>
                    <div className="flex items-center gap-1">
                        <Button
                            variant="ghost"
                            size="icon"
                            onClick={onPrevMonth}
                            data-dusk="prev-month"
                        >
                            <ChevronLeft className="size-4" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            onClick={onNextMonth}
                            data-dusk="next-month"
                        >
                            <ChevronRight className="size-4" />
                        </Button>
                    </div>
                </CardAction>
            </CardHeader>
            <CardContent>
                <div className="grid grid-cols-7 gap-1">
                    {WEEKDAY_NAMES_ES.map((name) => (
                        <div
                            key={name}
                            className="pb-2 text-center text-xs font-medium text-muted-foreground"
                        >
                            {name}
                        </div>
                    ))}

                    {weeks.flatMap((week) =>
                        week.map((day) => {
                            if (!day.isCurrentMonth) {
                                return (
                                    <div
                                        key={day.dateKey}
                                        className="flex h-16 flex-col items-center justify-center rounded-lg p-1 text-sm text-muted-foreground/30"
                                    >
                                        {day.date.getDate()}
                                    </div>
                                );
                            }

                            const ds = dayStatuses[day.dateKey];
                            const sc = serviceCounts[day.dateKey];
                            const bgClass = getDayColorClass(ds?.status);
                            const dotClass = getDayDotClass(ds?.status);
                            const isSelected = selectedDate === day.dateKey;

                            const isExecuted =
                                ds?.status === DayStatusEnum.Executed;
                            const executorName = ds?.executor?.name;
                            const executedAt = ds?.executed_at;

                            const cellContent = (
                                <button
                                    type="button"
                                    onClick={() => onDayClick(day.dateKey)}
                                    data-dusk={`day-${day.dateKey}`}
                                    className={cn(
                                        'flex h-16 w-full flex-col items-center justify-center gap-0.5 rounded-lg p-1 transition-colors hover:opacity-80',
                                        bgClass,
                                        day.isToday && 'ring-2 ring-primary',
                                        isSelected &&
                                            'ring-2 ring-blue-500 dark:ring-blue-400',
                                    )}
                                >
                                    <span className="text-sm font-medium">
                                        {day.date.getDate()}
                                    </span>
                                    <div
                                        className={cn(
                                            'size-2 rounded-full',
                                            dotClass,
                                        )}
                                    />
                                    {sc && (
                                        <span className="text-[10px] leading-none text-muted-foreground">
                                            {sc.total} serv.
                                        </span>
                                    )}
                                </button>
                            );

                            if (isExecuted && executorName) {
                                return (
                                    <Tooltip key={day.dateKey}>
                                        <TooltipTrigger asChild>
                                            {cellContent}
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <div>
                                                Ejecutado por {executorName}
                                            </div>
                                            {executedAt && (
                                                <div className="text-muted-foreground">
                                                    {format(
                                                        new Date(executedAt),
                                                        'dd/MM/yyyy HH:mm',
                                                    )}
                                                </div>
                                            )}
                                        </TooltipContent>
                                    </Tooltip>
                                );
                            }

                            return <div key={day.dateKey}>{cellContent}</div>;
                        }),
                    )}
                </div>
            </CardContent>
        </Card>
    );
}
