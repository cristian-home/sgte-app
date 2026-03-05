import { format } from 'date-fns';
import { X } from 'lucide-react';
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
    onClose: () => void;
}

function getDayColorClass(status: string | undefined): string {
    if (!status) return 'bg-neutral-200 dark:bg-neutral-800';
    if (status === DayStatusEnum.Projected) return 'bg-orange-500/20';
    if (status === DayStatusEnum.Executed) return 'bg-green-500/20';
    return 'bg-neutral-200 dark:bg-neutral-800';
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
    onClose,
}: MonthDetailProps) {
    const weeks = getWeeksOfMonth(year, month);

    return (
        <Card>
            <CardHeader>
                <CardTitle>
                    {MONTH_NAMES_ES[month]} {year}
                </CardTitle>
                <CardAction>
                    <Button variant="ghost" size="icon" onClick={onClose}>
                        <X className="size-4" />
                    </Button>
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
