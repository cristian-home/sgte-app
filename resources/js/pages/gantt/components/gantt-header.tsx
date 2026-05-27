import { Link, usePage } from '@inertiajs/react';
import { index as daySummaryIndex } from '@/actions/App/Http/Controllers/DaySummaryController';
import DateStepper from '@/components/date-stepper';
import { Button } from '@/components/ui/button';
import { viewerToday } from '@/lib/datetime';

interface GanttHeaderProps {
    /** Currently centered date in the timeline (controlled by the page). */
    date: string;
    canCreateServices: boolean;
    /**
     * Page-level callback that scrolls the timeline so `date` lands at
     * the center (noon of the day). Used by the chevrons + date picker.
     */
    onJumpToDate: (date: string) => void;
    /**
     * Page-level callback that scrolls the timeline so the current
     * instant lands at the center — the Now indicator's red vertical
     * line ends up in the viewport's horizontal middle. Used by the
     * Hoy button so "back to now" feels more precise than just
     * "back to today midnight".
     */
    onJumpToNow: () => void;
}

function isToday(dateStr: string, operationTz: string): boolean {
    return dateStr === viewerToday(operationTz);
}

export default function GanttHeader({
    date,
    onJumpToDate,
    onJumpToNow,
}: GanttHeaderProps) {
    const sharedConfig = usePage().props.config as
        | { operation_tz?: string }
        | undefined;
    const operationTz = sharedConfig?.operation_tz ?? 'America/Bogota';

    return (
        <div className="flex flex-wrap items-center gap-2">
            <DateStepper value={date} onChange={onJumpToDate} />

            {!isToday(date, operationTz) && (
                <Button
                    variant="outline"
                    size="sm"
                    className="h-8"
                    onClick={onJumpToNow}
                >
                    Hoy
                </Button>
            )}

            <div className="ml-auto">
                <Button variant="outline" size="sm" className="h-8" asChild>
                    <Link href={daySummaryIndex({ query: { date } }).url}>
                        Resumen
                    </Link>
                </Button>
            </div>
        </div>
    );
}
