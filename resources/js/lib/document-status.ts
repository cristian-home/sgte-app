/**
 * Shared helpers for "expiring document" pill components.
 *
 * Both `<VehicleDocumentPills />` (SOAT / RTM / Tarjeta de Operación)
 * and `<DriverLicensePill />` (Licencia) compute the same three-state
 * machine — `'expired' | 'expiring_soon' | 'ok'` — against today's
 * date with a 30-day "por vencer" window. Centralizing the math keeps
 * the dashboard, the vehicles index row tint, the drivers index row
 * tint, and the per-pill rendering all in lock-step.
 */

export type DocumentStatus = 'expired' | 'expiring_soon' | 'ok';

const DAYS_IN_MS = 24 * 60 * 60 * 1000;

/**
 * Days-ahead window used to flag "por vencer" documents. Mirrors the
 * server-side `DOCS_EXPIRY_WINDOW_DAYS` (VehicleController) and
 * `LICENSE_EXPIRY_WINDOW_DAYS` (DriverController) constants, plus the
 * dashboard's `EXPIRY_ALERT_DAYS`. Keep these in sync.
 */
export const EXPIRY_WINDOW_DAYS = 30;

/**
 * Spanish-locale formatter for due-date display. Reused everywhere so
 * the format is consistent across pills, show pages, dashboards.
 */
export const dateFormatter = new Intl.DateTimeFormat('es-CO', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
});

/**
 * Parse a backend-supplied due date into a Date instance. Accepts both
 * the short `Y-m-d` form (returned by helper methods like
 * `Carbon::toDateString()`) and the long ISO form
 * `Y-m-d\TH:i:s.uP` (returned by the default Eloquent `date` cast
 * serializer).
 *
 * Returns null when the input is null, empty, or unparseable.
 */
export function parseDueDate(dueDate: string | null): Date | null {
    if (!dueDate) {
        return null;
    }
    // Y-m-d is parsed as UTC midnight by the JS Date constructor on most
    // engines, which can yield "yesterday" in negative timezones. Append
    // a local-time component to anchor it to the user's wall clock.
    const isoCandidate = /^\d{4}-\d{2}-\d{2}$/.test(dueDate)
        ? `${dueDate}T00:00:00`
        : dueDate;
    const parsed = new Date(isoCandidate);
    if (Number.isNaN(parsed.getTime())) {
        return null;
    }
    return parsed;
}

/**
 * Compute the three-state status of a single due date relative to the
 * supplied `today` (defaulting to the local browser date).
 *
 * - `'expired'` — due date is null or strictly before today.
 * - `'expiring_soon'` — due date is within the next EXPIRY_WINDOW_DAYS days.
 * - `'ok'` — due date is more than EXPIRY_WINDOW_DAYS days away.
 */
export function documentStatus(
    dueDate: string | null,
    today?: string,
): DocumentStatus {
    const todayString = today ?? new Date().toISOString().slice(0, 10);
    const todayMs = new Date(`${todayString}T00:00:00`).getTime();
    return statusFor(dueDate, todayMs);
}

/**
 * Lower-level helper used by the pill components to avoid recomputing
 * `todayMs` for every pill in the same render. Exposes the same logic
 * as `documentStatus` but takes a pre-computed `todayMs`.
 */
export function statusFor(
    dueDate: string | null,
    todayMs: number,
): DocumentStatus {
    const parsed = parseDueDate(dueDate);
    if (parsed === null) {
        return 'expired';
    }
    const dueMs = parsed.getTime();
    if (dueMs < todayMs) {
        return 'expired';
    }
    const daysOut = Math.round((dueMs - todayMs) / DAYS_IN_MS);
    if (daysOut <= EXPIRY_WINDOW_DAYS) {
        return 'expiring_soon';
    }
    return 'ok';
}

/**
 * Map a status to the matching shadcn Badge variant.
 *
 * - `'expired'` → `destructive`
 * - `'expiring_soon'` → `secondary` (visually warning-tinted)
 * - `'ok'` → `outline`
 */
export function statusBadgeVariant(
    status: DocumentStatus,
): 'destructive' | 'secondary' | 'outline' {
    switch (status) {
        case 'expired':
            return 'destructive';
        case 'expiring_soon':
            return 'secondary';
        default:
            return 'outline';
    }
}

/**
 * Compute the local-browser `todayMs` once. Useful for components
 * that compute several `statusFor` calls in the same render.
 */
export function localTodayMs(today?: string): number {
    const todayString = today ?? new Date().toISOString().slice(0, 10);
    return new Date(`${todayString}T00:00:00`).getTime();
}

/**
 * Days-ahead window used to flag contracts as "por vencer".
 * Contracts have a longer renewal lead time than SOAT/RTM/license,
 * so we surface them earlier. Mirrors `CONTRACT_EXPIRY_ALERT_DAYS`
 * in DashboardController and the `contract_status` callback filter
 * in ContractController@index. Keep these in sync.
 */
export const CONTRACT_EXPIRY_WINDOW_DAYS = 60;

/**
 * Four-state temporal model for contracts — emerges from start_date,
 * end_date, and the manual `active` kill-switch:
 *
 * - `inactivo` — `active === false`. Manual close. Outranks the date axis.
 * - `vencido` — active but today is past `end_date` (action needed).
 * - `por_vencer` — active and `end_date` is within the next
 *    CONTRACT_EXPIRY_WINDOW_DAYS days.
 * - `vigente` — active and `end_date` is further out than the window
 *    (including future-dated contracts where `start_date > today`).
 */
export type ContractPeriodStatus =
    | 'vigente'
    | 'por_vencer'
    | 'vencido'
    | 'inactivo';

/**
 * Compute the four-state contract status against the supplied
 * `today` (defaulting to the local browser date). Mirrors the server
 * `applyContractStatusFilter` in ContractController — they must agree.
 */
export function contractPeriodStatus(
    contract: {
        start_date: string | null;
        end_date: string | null;
        active: boolean;
    },
    today?: string,
): ContractPeriodStatus {
    if (!contract.active) {
        return 'inactivo';
    }
    const parsedEnd = parseDueDate(contract.end_date);
    if (parsedEnd === null) {
        return 'vencido';
    }
    const todayMs = localTodayMs(today);
    const endMs = parsedEnd.getTime();
    if (endMs < todayMs) {
        return 'vencido';
    }
    const daysOut = Math.round((endMs - todayMs) / DAYS_IN_MS);
    if (daysOut <= CONTRACT_EXPIRY_WINDOW_DAYS) {
        return 'por_vencer';
    }
    return 'vigente';
}

/**
 * Compute the signed days-remaining from today until `end_date`.
 * Negative for expired contracts, 0 for "ends today".
 */
export function contractDaysRemaining(
    endDate: string | null,
    today?: string,
): number | null {
    const parsed = parseDueDate(endDate);
    if (parsed === null) {
        return null;
    }
    const todayMs = localTodayMs(today);
    return Math.round((parsed.getTime() - todayMs) / DAYS_IN_MS);
}

/**
 * Map a contract status to the matching shadcn Badge variant.
 */
export function contractStatusBadgeVariant(
    status: ContractPeriodStatus,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (status) {
        case 'vigente':
            return 'default';
        case 'por_vencer':
            return 'secondary';
        case 'vencido':
            return 'destructive';
        default:
            return 'outline';
    }
}
