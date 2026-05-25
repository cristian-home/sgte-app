import {
    eachDayOfInterval,
    endOfMonth,
    endOfWeek,
    format,
    isSameMonth,
    isToday as isTodayFn,
    startOfMonth,
    startOfWeek,
} from 'date-fns';

export const MONTH_NAMES_ES = [
    'Enero',
    'Febrero',
    'Marzo',
    'Abril',
    'Mayo',
    'Junio',
    'Julio',
    'Agosto',
    'Septiembre',
    'Octubre',
    'Noviembre',
    'Diciembre',
];

export const WEEKDAY_NAMES_ES = [
    'Lun',
    'Mar',
    'Mié',
    'Jue',
    'Vie',
    'Sáb',
    'Dom',
];

export function formatDateKey(date: Date): string {
    return format(date, 'yyyy-MM-dd');
}

/**
 * Add `days` to a `Y-m-d` string and return a new `Y-m-d` string.
 * Anchors at midday to avoid DST hour-shift edge cases.
 */
export function addDays(dateStr: string, days: number): string {
    const d = new Date(dateStr + 'T12:00:00');
    d.setDate(d.getDate() + days);
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

/**
 * Format a `Y-m-d` string as a long Spanish date
 * (e.g. "viernes, 8 de mayo de 2026"), parsing at midday to avoid DST.
 */
export function formatDateEs(dateStr: string): string {
    const d = new Date(dateStr + 'T12:00:00');
    return d.toLocaleDateString('es-CO', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
}

export { isTodayFn as isToday };

export interface CalendarDay {
    date: Date;
    dateKey: string;
    isCurrentMonth: boolean;
    isToday: boolean;
}

export function getWeeksOfMonth(year: number, month: number): CalendarDay[][] {
    const monthStart = startOfMonth(new Date(year, month, 1));
    const monthEnd = endOfMonth(monthStart);
    const calendarStart = startOfWeek(monthStart, { weekStartsOn: 1 });
    const calendarEnd = endOfWeek(monthEnd, { weekStartsOn: 1 });

    const allDays = eachDayOfInterval({
        start: calendarStart,
        end: calendarEnd,
    });

    const weeks: CalendarDay[][] = [];
    for (let i = 0; i < allDays.length; i += 7) {
        weeks.push(
            allDays.slice(i, i + 7).map((date) => ({
                date,
                dateKey: formatDateKey(date),
                isCurrentMonth: isSameMonth(date, monthStart),
                isToday: isTodayFn(date),
            })),
        );
    }

    return weeks;
}
