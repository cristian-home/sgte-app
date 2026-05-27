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
     * the center. Replaces the previous `router.get` Inertia
     * navigation — date changes are now pure scroll, no SSR reload.
     */
    onJumpToDate: (date: string) => void;
}

function isToday(dateStr: string, operationTz: string): boolean {
    return dateStr === viewerToday(operationTz);
}

export default function GanttHeader({ date, onJumpToDate }: GanttHeaderProps) {
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
                    onClick={() => onJumpToDate(viewerToday(operationTz))}
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
