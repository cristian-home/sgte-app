import { Badge } from '@/components/ui/badge';

interface ServiceTimelineBarProps {
    plannedStartTime: string;
    plannedDuration: number;
    actualStartTime: string | null;
    actualEndTime: string | null;
}

function timeToMinutes(time: string): number {
    const [h, m] = time.split(':').map(Number);
    return h * 60 + m;
}

function formatTime(time: string): string {
    return time.substring(0, 5);
}

export function ServiceTimelineBar({
    plannedStartTime,
    plannedDuration,
    actualStartTime,
    actualEndTime,
}: ServiceTimelineBarProps) {
    const plannedStart = timeToMinutes(plannedStartTime);
    const plannedEnd = plannedStart + plannedDuration;

    const hasActual = actualStartTime !== null && actualEndTime !== null;
    const actualStart = hasActual ? timeToMinutes(actualStartTime) : null;
    const actualEnd = hasActual ? timeToMinutes(actualEndTime) : null;

    const actualDuration =
        actualStart !== null && actualEnd !== null
            ? actualEnd - actualStart
            : null;

    const axisMin = Math.min(plannedStart, actualStart ?? plannedStart) - 30;
    const axisMax = Math.max(plannedEnd, actualEnd ?? plannedEnd) + 30;
    const axisRange = axisMax - axisMin;

    const pctLeft = (val: number) => `${((val - axisMin) / axisRange) * 100}%`;
    const pctWidth = (start: number, end: number) =>
        `${((end - start) / axisRange) * 100}%`;

    return (
        <div className="space-y-3">
            {/* Planned bar */}
            <div className="space-y-1">
                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                    <span>Planificado</span>
                    <Badge variant="secondary" className="text-[10px]">
                        {plannedDuration} min
                    </Badge>
                </div>
                <div className="relative h-5 w-full rounded bg-muted/50">
                    <div
                        className="absolute top-0 flex h-full items-center rounded bg-blue-500/70"
                        style={{
                            left: pctLeft(plannedStart),
                            width: pctWidth(plannedStart, plannedEnd),
                        }}
                    >
                        <span className="truncate px-1.5 text-[10px] font-medium text-white">
                            {formatTime(plannedStartTime)}
                        </span>
                    </div>
                </div>
            </div>

            {/* Actual bar */}
            <div className="space-y-1">
                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                    <span>Real</span>
                    {actualDuration !== null && actualDuration > 0 && (
                        <Badge variant="secondary" className="text-[10px]">
                            {actualDuration} min
                        </Badge>
                    )}
                </div>
                <div className="relative h-5 w-full rounded bg-muted/50">
                    {hasActual && actualStart !== null && actualEnd !== null ? (
                        <div
                            className="absolute top-0 flex h-full items-center rounded bg-emerald-500/70"
                            style={{
                                left: pctLeft(actualStart),
                                width: pctWidth(actualStart, actualEnd),
                            }}
                        >
                            <span className="truncate px-1.5 text-[10px] font-medium text-white">
                                {formatTime(actualStartTime!)}
                            </span>
                        </div>
                    ) : (
                        <div
                            className="absolute top-0 flex h-full items-center rounded border border-dashed border-muted-foreground/30"
                            style={{
                                left: pctLeft(plannedStart),
                                width: pctWidth(plannedStart, plannedEnd),
                            }}
                        >
                            <span className="px-1.5 text-[10px] text-muted-foreground">
                                Sin datos
                            </span>
                        </div>
                    )}
                </div>
            </div>

            {/* Legend */}
            <div className="flex gap-4 text-[10px] text-muted-foreground">
                <div className="flex items-center gap-1">
                    <div className="h-2 w-2 rounded-full bg-blue-500/70" />
                    <span>Planificado</span>
                </div>
                <div className="flex items-center gap-1">
                    <div className="h-2 w-2 rounded-full bg-emerald-500/70" />
                    <span>Real</span>
                </div>
            </div>
        </div>
    );
}
