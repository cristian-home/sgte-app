import { router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { index as ganttIndex } from '@/actions/App/Http/Controllers/GanttController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import type { DayStatus, Municipality } from '@/types/models';

interface GanttHeaderProps {
    date: string;
    municipalityId: number | null;
    municipalities: Pick<Municipality, 'id' | 'name'>[];
    dayStatus: DayStatus | null;
    canCreateServices: boolean;
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

function isToday(dateStr: string): boolean {
    return dateStr === new Date().toISOString().slice(0, 10);
}

export default function GanttHeader({
    date,
    municipalityId,
    municipalities,
    dayStatus,
}: GanttHeaderProps) {
    const isExecuted = dayStatus?.status === 'executed';

    function navigate(newDate: string, newMunicipalityId?: number | null) {
        const params: Record<string, string | number> = { date: newDate };
        const mId =
            newMunicipalityId !== undefined
                ? newMunicipalityId
                : municipalityId;
        if (mId) {
            params.municipality_id = mId;
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
                        className="h-8 w-8"
                        onClick={() => navigate(addDays(date, -1))}
                    >
                        <ChevronLeft className="h-4 w-4" />
                    </Button>
                    <Button
                        variant="outline"
                        size="icon"
                        className="h-8 w-8"
                        onClick={() => navigate(addDays(date, 1))}
                    >
                        <ChevronRight className="h-4 w-4" />
                    </Button>
                </div>

                <Input
                    type="date"
                    value={date}
                    onChange={(e) => {
                        if (e.target.value) navigate(e.target.value);
                    }}
                    className="h-8 w-auto"
                />

                {!isToday(date) && (
                    <Button
                        variant="outline"
                        size="sm"
                        className="h-8"
                        onClick={() =>
                            navigate(new Date().toISOString().slice(0, 10))
                        }
                    >
                        Hoy
                    </Button>
                )}

                <span className="text-sm font-medium capitalize">
                    {formatDateEs(date)}
                </span>

                <div className="ml-auto flex items-center gap-3">
                    <Select
                        value={municipalityId?.toString() ?? 'all'}
                        onValueChange={(val) =>
                            navigate(date, val === 'all' ? null : Number(val))
                        }
                    >
                        <SelectTrigger className="h-8 w-50">
                            <SelectValue placeholder="Municipio" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">
                                Todos los municipios
                            </SelectItem>
                            {municipalities.map((m) => (
                                <SelectItem key={m.id} value={m.id.toString()}>
                                    {m.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

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
                    Dia Ejecutado — No se pueden crear nuevos servicios en este
                    dia.
                </div>
            )}
        </div>
    );
}
