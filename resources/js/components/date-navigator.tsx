import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { addDays, formatDateEs } from '@/lib/date-utils';
import { viewerToday } from '@/lib/datetime';

interface DateNavigatorProps {
    /** Currently selected day, `Y-m-d`. */
    date: string;
    /** Operation TZ used to compare against "today" (controls when the Hoy button is visible). */
    operationTz: string;
    /** Called with the new `Y-m-d` whenever the user navigates. */
    onDateChange: (newDate: string) => void;
    /** Show the "Hoy" button when `date` differs from today in `operationTz`. Defaults to true. */
    showTodayButton?: boolean;
    /** Show the "viernes, 8 de mayo de 2026"-style label next to the controls. Defaults to true. */
    showFormattedLabel?: boolean;
}

/**
 * Reusable date picker pager: ChevronLeft / ChevronRight (±1 day),
 * a `<input type="date">` to jump to an arbitrary date, and an
 * optional "Hoy" shortcut that returns to today in operation TZ.
 *
 * The component is purely controlled — the parent owns the date in URL
 * (or wherever) and reacts to `onDateChange` by calling `router.get(...)`
 * or equivalent.
 */
export function DateNavigator({
    date,
    operationTz,
    onDateChange,
    showTodayButton = true,
    showFormattedLabel = true,
}: DateNavigatorProps) {
    const today = viewerToday(operationTz);
    const isToday = date === today;

    return (
        <div className="flex flex-wrap items-center gap-3">
            <div className="flex items-center gap-1">
                <Button
                    variant="outline"
                    size="icon"
                    className="size-8"
                    onClick={() => onDateChange(addDays(date, -1))}
                    aria-label="Día anterior"
                >
                    <ChevronLeft className="size-4" />
                </Button>
                <Button
                    variant="outline"
                    size="icon"
                    className="size-8"
                    onClick={() => onDateChange(addDays(date, 1))}
                    aria-label="Día siguiente"
                >
                    <ChevronRight className="size-4" />
                </Button>
            </div>

            <Input
                type="date"
                value={date}
                onChange={(e) => {
                    if (e.target.value) onDateChange(e.target.value);
                }}
                className="h-8 w-auto"
            />

            {showTodayButton && !isToday && (
                <Button
                    variant="outline"
                    size="sm"
                    className="h-8"
                    onClick={() => onDateChange(today)}
                >
                    Hoy
                </Button>
            )}

            {showFormattedLabel && (
                <span className="text-sm font-medium capitalize">
                    {formatDateEs(date)}
                </span>
            )}
        </div>
    );
}

export default DateNavigator;
