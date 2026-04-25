export const GANTT_START_HOUR = 5;
export const GANTT_END_HOUR = 23;
export const TOTAL_HOURS = GANTT_END_HOUR - GANTT_START_HOUR;

export const HOUR_LABELS = Array.from({ length: TOTAL_HOURS }, (_, i) => {
    const h = GANTT_START_HOUR + i;
    return `${h.toString().padStart(2, '0')}:00`;
});

export function timeToHours(time: string): number {
    const [h, m] = time.split(':').map(Number);
    return h + m / 60;
}

/**
 * Convert a UTC instant to fractional hours-of-day in `eventTz` for the
 * given grid date. Used by `serviceBarPosition` so a Gantt anchored on
 * 2026-04-24 in Bogotá positions a 14:30 Bogotá service correctly even
 * if the underlying instant is `2026-04-24T19:30:00Z`.
 */
function instantToHoursInTz(at: string, eventTz: string): number {
    const fmt = new Intl.DateTimeFormat('en-GB', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
        timeZone: eventTz,
    });
    const [hStr, mStr] = fmt.format(new Date(at)).split(':');
    return Number(hStr) + Number(mStr) / 60;
}

export function serviceBarPosition(
    plannedStartAt: string,
    plannedDuration: number,
    eventTz: string,
): { left: number; width: number } | null {
    const startHours = instantToHoursInTz(plannedStartAt, eventTz);
    const durationHours = plannedDuration / 60;

    const visibleStart = Math.max(startHours, GANTT_START_HOUR);
    const visibleEnd = Math.min(startHours + durationHours, GANTT_END_HOUR);

    if (visibleStart >= GANTT_END_HOUR || visibleEnd <= GANTT_START_HOUR) {
        return null;
    }

    const left = ((visibleStart - GANTT_START_HOUR) / TOTAL_HOURS) * 100;
    const width = ((visibleEnd - visibleStart) / TOTAL_HOURS) * 100;
    return { left, width };
}

export function computeVehicleDocStatus(
    vehicle: {
        soat_due_date: string | null;
        rtm_due_date: string | null;
        operation_card_due_date: string | null;
    },
    today: string,
): { isBlocked: boolean; hasWarning: boolean; expiredDocs: string[] } {
    const docs = [
        { name: 'SOAT', date: vehicle.soat_due_date },
        { name: 'RTM', date: vehicle.rtm_due_date },
        { name: 'T.O.', date: vehicle.operation_card_due_date },
    ];

    const expiredDocs = docs
        .filter((d) => d.date && d.date < today)
        .map((d) => d.name);
    const isBlocked = expiredDocs.length > 0;

    const warningDate = new Date(today);
    warningDate.setDate(warningDate.getDate() + 15);
    const warningDateStr = warningDate.toISOString().slice(0, 10);

    const hasWarning =
        !isBlocked &&
        docs.some((d) => d.date && d.date >= today && d.date <= warningDateStr);

    return { isBlocked, hasWarning, expiredDocs };
}

export function formatHour(hour: number): string {
    const h = Math.floor(hour);
    const m = Math.round((hour - h) * 60);
    return `${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}`;
}
