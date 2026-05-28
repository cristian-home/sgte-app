import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

interface DateStepperProps {
    value: string;
    onChange: (next: string) => void;
    className?: string;
}

function addDays(dateStr: string, days: number): string {
    const d = new Date(dateStr + 'T12:00:00');
    d.setDate(d.getDate() + days);
    return d.toISOString().slice(0, 10);
}

/**
 * Compact `‹ | date-picker | ›` stepper. The three controls share a
 * single rounded border so they read as one "date selector" rather
 * than three floating buttons — frees toolbar real estate on mobile
 * where the Gantt + Day Summary headers used to wrap awkwardly.
 *
 * Pair with an external Hoy button (and a separate date label /
 * status badge in a sub-header) when those affordances are needed —
 * keeping them outside this widget avoids visual lumpiness on days
 * where Hoy is hidden.
 */
export default function DateStepper({
    value,
    onChange,
    className,
}: DateStepperProps) {
    return (
        <div
            className={cn(
                'inline-flex h-8 items-stretch overflow-hidden rounded-md border border-input',
                className,
            )}
        >
            <Button
                type="button"
                variant="ghost"
                size="icon"
                className="size-8 rounded-none"
                onClick={() => onChange(addDays(value, -1))}
                aria-label="Día anterior"
            >
                <ChevronLeft className="size-4" />
            </Button>
            <Input
                type="date"
                value={value}
                onChange={(e) => {
                    if (e.target.value) onChange(e.target.value);
                }}
                className="h-8 w-auto rounded-none border-x border-y-0 border-input shadow-none focus-visible:ring-0 focus-visible:ring-offset-0"
            />
            <Button
                type="button"
                variant="ghost"
                size="icon"
                className="size-8 rounded-none"
                onClick={() => onChange(addDays(value, 1))}
                aria-label="Día siguiente"
            >
                <ChevronRight className="size-4" />
            </Button>
        </div>
    );
}
