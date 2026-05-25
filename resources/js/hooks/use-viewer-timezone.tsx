import { router, usePage } from '@inertiajs/react';
import { useEffect } from 'react';

const COOKIE_NAME = 'viewer_tz';
const COOKIE_DAYS = 365;

function setCookie(name: string, value: string, days = COOKIE_DAYS): void {
    if (typeof document === 'undefined') return;
    const maxAge = days * 24 * 60 * 60;
    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
}

function readCookie(name: string): string | null {
    if (typeof document === 'undefined') return null;
    const prefix = `${name}=`;
    for (const part of document.cookie.split(';')) {
        const trimmed = part.trim();
        if (trimmed.startsWith(prefix)) {
            return decodeURIComponent(trimmed.slice(prefix.length));
        }
    }
    return null;
}

function detectViewerTimezone(): string | null {
    if (typeof Intl === 'undefined') return null;
    try {
        return Intl.DateTimeFormat().resolvedOptions().timeZone || null;
    } catch {
        return null;
    }
}

/**
 * Detects the browser's IANA timezone, persists it as a `viewer_tz` cookie,
 * and triggers a partial Inertia reload of the `config` shared prop the
 * first time it diverges from what the backend has. Pair with
 * `App\Http\Middleware\CaptureViewerTimezone` on the backend, which reads
 * the cookie and `users.timezone`.
 *
 * The cookie is read on every server render, so the next *full* visit
 * naturally picks the freshest value without an extra request. The
 * partial reload only fires once per browser session per change.
 */
export function useViewerTimezone(): string | null {
    const sharedConfig = usePage().props.config as
        | { operation_tz?: string; viewer_tz?: string }
        | undefined;

    useEffect(() => {
        const detected = detectViewerTimezone();
        if (!detected) return;

        const stored = readCookie(COOKIE_NAME);
        const sharedViewerTz = sharedConfig?.viewer_tz ?? null;

        if (stored !== detected) {
            setCookie(COOKIE_NAME, detected);
        }

        if (sharedViewerTz !== detected) {
            router.reload({ only: ['config'] });
        }
    }, [sharedConfig?.viewer_tz]);

    return sharedConfig?.viewer_tz ?? sharedConfig?.operation_tz ?? null;
}
