/**
 * Centralized datetime helpers.
 *
 * Two modes of rendering:
 *
 * 1. **Event-TZ rendering** (`formatEventTime`, `formatEventDate`,
 *    `formatEventDateTime`, `formatTimeRange`) — renders an instant in the
 *    timezone the event is operationally anchored to (e.g. a service's own
 *    `timezone` column). Used for service / contract / Gantt / Day Summary
 *    views so a service planned at 14:30 Bogotá always reads "14:30" no
 *    matter where the viewer is.
 *
 * 2. **Viewer-TZ rendering** (`formatTimestampInViewerTz`) — renders an
 *    instant in the viewer's local timezone. Used for genuine audit-style
 *    timestamps (`created_at`, `updated_at`, audit-log entries,
 *    notifications) so each user sees timestamps anchored to their own
 *    locale.
 *
 * Implementation notes:
 *   - All formatters use the native `Intl.DateTimeFormat` — no
 *     `date-fns-tz` or other npm dependency.
 *   - Each helper accepts an optional `viewerTzOverride` so a future
 *     "view in my local TZ" toggle can swap event-TZ formatting for
 *     viewer-TZ formatting without UI rewrites.
 *   - Inputs are tolerant: ISO strings, `Date`, `null`, and `undefined`
 *     all return `''` when the value can't be rendered, so call sites
 *     can render the result directly without per-call null guards.
 */

const DEFAULT_LOCALE = 'es-CO';

/**
 * Inputs that the helpers accept. ISO 8601 strings are the wire format
 * for instants in this project; `Date` is also tolerated for code paths
 * that have already parsed a value.
 */
export type DateLike = string | Date | null | undefined;

interface BaseOptions {
    /** Override the locale used for formatting. Defaults to es-CO. */
    locale?: string;
    /**
     * When the viewer-TZ toggle is wired up, callers can pass the
     * viewer's IANA timezone to render in the viewer's TZ instead of
     * the event TZ. Keeps the call site stable for future UI work.
     */
    viewerTzOverride?: string;
}

interface TimeOptions extends BaseOptions {
    /** Force 24-hour vs 12-hour formatting. Defaults to 24h. */
    hour12?: boolean;
}

function toDate(value: DateLike): Date | null {
    if (value === null || value === undefined || value === '') {
        return null;
    }
    if (value instanceof Date) {
        return Number.isNaN(value.getTime()) ? null : value;
    }
    const parsed = new Date(value);
    return Number.isNaN(parsed.getTime()) ? null : parsed;
}

function resolveTz(eventTz: string, opts?: BaseOptions): string {
    return opts?.viewerTzOverride ?? eventTz;
}

/**
 * Format the wall-clock time-of-day of an instant in the event's
 * timezone (HH:mm). Returns an empty string for null / invalid input.
 */
export function formatEventTime(
    at: DateLike,
    eventTz: string,
    opts?: TimeOptions,
): string {
    const date = toDate(at);
    if (!date) {
        return '';
    }
    const tz = resolveTz(eventTz, opts);
    return new Intl.DateTimeFormat(opts?.locale ?? DEFAULT_LOCALE, {
        hour: '2-digit',
        minute: '2-digit',
        hour12: opts?.hour12 ?? false,
        timeZone: tz,
    }).format(date);
}

/**
 * Format the wall-clock day-of-the-month of an instant in the event's
 * timezone (default: dd/MM/yyyy es-CO style).
 */
export function formatEventDate(
    at: DateLike,
    eventTz: string,
    opts?: BaseOptions & {
        dateStyle?: Intl.DateTimeFormatOptions['dateStyle'];
    },
): string {
    const date = toDate(at);
    if (!date) {
        return '';
    }
    const tz = resolveTz(eventTz, opts);
    return new Intl.DateTimeFormat(opts?.locale ?? DEFAULT_LOCALE, {
        dateStyle: opts?.dateStyle ?? 'short',
        timeZone: tz,
    }).format(date);
}

/**
 * Combined wall-clock day + time-of-day in the event's timezone.
 */
export function formatEventDateTime(
    at: DateLike,
    eventTz: string,
    opts?: TimeOptions & {
        dateStyle?: Intl.DateTimeFormatOptions['dateStyle'];
    },
): string {
    const date = toDate(at);
    if (!date) {
        return '';
    }
    const tz = resolveTz(eventTz, opts);
    return new Intl.DateTimeFormat(opts?.locale ?? DEFAULT_LOCALE, {
        dateStyle: opts?.dateStyle ?? 'short',
        timeStyle: 'short',
        hour12: opts?.hour12 ?? false,
        timeZone: tz,
    }).format(date);
}

/**
 * Render a HH:mm – HH:mm range in the event's timezone, gracefully
 * degrading to "HH:mm" + "—" when only one endpoint is provided.
 */
export function formatTimeRange(
    startAt: DateLike,
    endAt: DateLike,
    eventTz: string,
    opts?: TimeOptions,
): string {
    const start = formatEventTime(startAt, eventTz, opts);
    const end = formatEventTime(endAt, eventTz, opts);
    if (!start && !end) {
        return '';
    }
    if (start && !end) {
        return `${start} – —`;
    }
    if (!start && end) {
        return `— – ${end}`;
    }
    return `${start} – ${end}`;
}

/**
 * Render an instant in the *viewer's* timezone — i.e. the browser's
 * local TZ, not an event-anchored one. Use for `created_at`,
 * `updated_at`, audit-log entries, etc. where the value is genuinely
 * an instant the viewer wants in their own clock.
 */
export function formatTimestampInViewerTz(
    at: DateLike,
    opts?: TimeOptions & {
        dateStyle?: Intl.DateTimeFormatOptions['dateStyle'];
    },
): string {
    const date = toDate(at);
    if (!date) {
        return '';
    }
    return new Intl.DateTimeFormat(opts?.locale ?? DEFAULT_LOCALE, {
        dateStyle: opts?.dateStyle ?? 'short',
        timeStyle: 'short',
        hour12: opts?.hour12 ?? false,
        timeZone: opts?.viewerTzOverride,
    }).format(date);
}
