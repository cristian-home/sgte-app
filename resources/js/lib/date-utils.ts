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
