/**
 * Tiny structured-logging helper used by the address autocomplete and
 * the map picker modal.
 *
 * Visibility model:
 *
 * - `dlog` / `dperf` go through `console.debug`. Browsers hide that
 *   level by default in DevTools (user must flip "Verbose" / "Debug"
 *   filter to see it). Playwright captures it only when explicitly
 *   passed `level: "debug"`.
 * - `dwarn` goes through `console.warn` for genuine error signals
 *   (network down, response not OK). Visible at the default DevTools
 *   filter — that's intentional, devs and QA *should* see those.
 *
 * Both `dlog` and `dperf` are gated behind `VITE_APP_DEBUG`, which
 * `.env` interpolates from Laravel's `APP_DEBUG`. One toggle controls
 * server-side `config('app.debug')` and browser-side debug logs at
 * the same time. In production builds with `APP_DEBUG=false` the gate
 * resolves to a compile-time `false`, so Vite tree-shakes the calls
 * out of the bundle entirely.
 *
 * `dev` builds (`npm run dev`) keep logs on regardless, so local
 * development is unaffected by what's in `.env`.
 *
 * Entry points:
 *
 * - `dlog(channel, event, data?)` — fire-and-forget structured event.
 * - `dperf(channel, event, data?)` — START + DONE pair with duration.
 *   Returns a `done(extra?)` thunk; call it on resolve / reject /
 *   abort to emit a matching DONE line with `duration_ms`.
 * - `dwarn(channel, event, data?)` — error-level structured event.
 *   Always emitted regardless of the debug gate.
 */

type LogPayload = Record<string, unknown> | undefined;

const debugEnabled =
    import.meta.env.DEV || isTruthy(import.meta.env.VITE_APP_DEBUG as unknown);

function isTruthy(value: unknown): boolean {
    if (typeof value === 'boolean') return value;
    if (typeof value !== 'string') return false;
    const v = value.toLowerCase().trim();
    return v === 'true' || v === '1';
}

function emit(level: 'debug' | 'warn', label: string, data: LogPayload): void {
    if (data === undefined) {
        console[level](label);
        return;
    }
    // Stringify so the full payload survives DevTools / Playwright's
    // structured-clone truncation. Falls back gracefully on cyclical
    // structures (which we don't pass anyway).
    let serialized: string;
    try {
        serialized = JSON.stringify(data);
    } catch {
        serialized = String(data);
    }
    console[level](`${label} ${serialized}`);
}

export function dlog(channel: string, event: string, data?: LogPayload): void {
    if (!debugEnabled) return;
    emit('debug', `[${channel}] ${event}`, data);
}

export function dwarn(channel: string, event: string, data?: LogPayload): void {
    // Warnings are always emitted — they signal genuine errors
    // (network down, mapbox response not OK) and devs / QA need to
    // see them at the default DevTools filter regardless of
    // APP_DEBUG.
    emit('warn', `[${channel}] ${event}`, data);
}

export function dperf(
    channel: string,
    event: string,
    data?: LogPayload,
): (extra?: LogPayload) => void {
    if (!debugEnabled) {
        // No-op closure when debug is off, so call sites don't have
        // to branch.
        return () => {};
    }
    const start =
        typeof performance !== 'undefined' && performance.now
            ? performance.now()
            : Date.now();
    emit('debug', `[${channel}] ${event} START`, data);
    return (extra?: LogPayload) => {
        const elapsed =
            typeof performance !== 'undefined' && performance.now
                ? performance.now()
                : Date.now();
        const duration_ms = Math.round(elapsed - start);
        emit('debug', `[${channel}] ${event} DONE`, {
            duration_ms,
            ...(extra ?? {}),
        });
    };
}
