import { Link, usePage } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { index as daySummaryIndex } from '@/actions/App/Http/Controllers/DaySummaryController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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

function addDays(dateStr: string, days: number): string {
    const d = new Date(dateStr + 'T12:00:00');
    d.setDate(d.getDate() + days);
    return d.toISOString().slice(0, 10);
}

function formatDateEs(dateStr: string): string {
    const d = new Date(dateStr + 'T12:00:00');
    return d.toLocaleDateString('es-CO', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
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
        <div>
            <div className="flex flex-wrap items-center gap-3">
                <div className="flex items-center gap-1">
                    <Button
                        variant="outline"
                        size="icon"
                        className="size-8"
                        onClick={() => onJumpToDate(addDays(date, -1))}
                    >
                        <ChevronLeft className="size-4" />
                    </Button>
                    <Button
                        variant="outline"
                        size="icon"
                        className="size-8"
                        onClick={() => onJumpToDate(addDays(date, 1))}
                    >
                        <ChevronRight className="size-4" />
                    </Button>
                </div>

                <Input
                    type="date"
                    value={date}
                    onChange={(e) => {
                        if (e.target.value) onJumpToDate(e.target.value);
                    }}
                    className="h-8 w-auto"
                />

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

                {/* Date label — hidden on mobile because the date
                    picker already shows DD/MM/YYYY, and on a 375px
                    viewport the verbose "martes, 26 de mayo de 2026"
                    would push every other control off-screen. */}
                <span className="hidden text-sm font-medium capitalize sm:inline">
                    {formatDateEs(date)}
                </span>

                <div className="flex flex-1 items-center justify-end">
                    <Button variant="outline" size="sm" className="h-8" asChild>
                        <Link href={daySummaryIndex({ query: { date } }).url}>
                            Resumen
                        </Link>
                    </Button>
                </div>
            </div>
        </div>
    );
}
