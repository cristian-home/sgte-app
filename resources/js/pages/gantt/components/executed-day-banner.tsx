import type { DayStatus } from '@/types/models';

interface Props {
    dayStatus: DayStatus;
}

const longDateFormatter = new Intl.DateTimeFormat('es-CO', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
});

/**
 * Floating banner shown above the timeline whenever the currently
 * centered day is marked Executed. Reactive to horizontal scroll —
 * the page derives the centered day's status from the same per-day
 * cache that powers the inline DaySeparator badges.
 */
export default function ExecutedDayBanner({ dayStatus }: Props) {
    const [y, m, d] = dayStatus.date.split('-').map(Number);
    const label = longDateFormatter.format(new Date(Date.UTC(y, m - 1, d, 12)));

    return (
        <div className="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700 dark:border-green-800 dark:bg-green-950 dark:text-green-300">
            <span className="font-medium capitalize">{label}</span> — Día
            ejecutado. No se pueden crear nuevos servicios en este día.
        </div>
    );
}
