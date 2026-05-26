import { router, usePage } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { index as daySummaryIndex } from '@/actions/App/Http/Controllers/DaySummaryController';
import { index as ganttIndex } from '@/actions/App/Http/Controllers/GanttController';
import MunicipalityCombobox, {
    type MunicipalityOption,
} from '@/components/municipality-combobox';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { viewerToday } from '@/lib/datetime';
import { cn } from '@/lib/utils';
import type { DayStatus } from '@/types/models';

interface GanttHeaderProps {
    /** Currently centered date in the timeline (controlled by the page). */
    date: string;
    municipalityId: number | null;
    municipalities: MunicipalityOption[];
    dayStatus: DayStatus | null;
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

export default function GanttHeader({
    date,
    municipalityId,
    municipalities,
    dayStatus,
    onJumpToDate,
}: GanttHeaderProps) {
    const isExecuted = dayStatus?.status === 'executed';
    const sharedConfig = usePage().props.config as
        | { operation_tz?: string }
        | undefined;
    const operationTz = sharedConfig?.operation_tz ?? 'America/Bogota';

    // Municipality change is a structural filter (different vehicle
    // list) so it still does a full Inertia navigate to re-render the
    // SSR payload. Date changes are purely visual — the page handles
    // them via `onJumpToDate` and the new day is fetched via the JSON
    // branch of the same endpoint.
    function navigateMunicipality(newMunicipalityId: number | null) {
        const params: Record<string, string | number> = { date };
        if (newMunicipalityId) {
            params.municipality_id = newMunicipalityId;
        }
        router.get(ganttIndex().url, params, {
            preserveState: true,
            preserveScroll: true,
        });
    }

    return (
        <div className="space-y-2">
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

                {/* Right-side cluster. flex-wrap is critical: without
                    it the badge gets clipped on narrow viewports
                    because the combo's fixed width starves it. With
                    flex-wrap the badge falls to its own row instead
                    of disappearing. */}
                <div className="flex flex-1 flex-wrap items-center justify-end gap-2 sm:gap-3">
                    <Button variant="outline" size="sm" className="h-8" asChild>
                        <a href={daySummaryIndex({ query: { date } }).url}>
                            Resumen
                        </a>
                    </Button>
                    <MunicipalityCombobox
                        municipalities={municipalities}
                        value={municipalityId}
                        onChange={(val) =>
                            navigateMunicipality(val ? Number(val) : null)
                        }
                        placeholder="Todos los municipios"
                        // Mobile: take whatever horizontal space is
                        // left in the row (min-w-0 lets it shrink).
                        // Tablet+: pin to 240px so it doesn't sprawl.
                        className="min-w-0 max-w-full flex-1 sm:w-60 sm:flex-none"
                    />

                    {dayStatus && (
                        <Badge
                            className={cn(
                                isExecuted
                                    ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300'
                                    : 'bg-orange-100 text-orange-700 dark:bg-orange-900 dark:text-orange-300',
                            )}
                        >
                            {isExecuted ? 'Ejecutado' : 'Proyectado'}
                        </Badge>
                    )}
                </div>
            </div>

            {isExecuted && (
                <div className="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700 dark:border-green-800 dark:bg-green-950 dark:text-green-300">
                    Día Ejecutado — No se pueden crear nuevos servicios en este
                    día.
                </div>
            )}
        </div>
    );
}
