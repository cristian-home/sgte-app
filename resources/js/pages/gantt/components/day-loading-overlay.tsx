import { useEffect, useState } from 'react';

interface Props {
    leftPx: number;
    widthPx: number;
    topPx: number;
    heightPx: number;
    /**
     * Wait this long before painting the shimmer. Suppresses flicker
     * for fast fetches (~80% of cache misses come back in <100ms).
     */
    delayMs?: number;
}

/**
 * Subtle pulsing overlay shown over a day-segment while its services
 * are being fetched. The delay prevents flicker on quick responses; the
 * pulse is opacity-based so the underlying gridlines + AHORA indicator
 * stay readable. `pointer-events: none` so clicks fall through to the
 * row's empty-cell handler.
 */
export default function DayLoadingOverlay({
    leftPx,
    widthPx,
    topPx,
    heightPx,
    delayMs = 200,
}: Props) {
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        const id = window.setTimeout(() => setVisible(true), delayMs);
        return () => window.clearTimeout(id);
    }, [delayMs]);

    if (!visible) return null;

    return (
        <div
            aria-hidden
            className="pointer-events-none absolute z-[5] animate-pulse bg-foreground/5"
            style={{
                left: `${leftPx}px`,
                top: `${topPx}px`,
                width: `${widthPx}px`,
                height: `${heightPx}px`,
            }}
        />
    );
}
