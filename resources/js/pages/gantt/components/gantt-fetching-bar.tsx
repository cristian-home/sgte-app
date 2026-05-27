import { useEffect, useState } from 'react';

interface Props {
    /** When true, the bar is meant to be visible (subject to delayMs). */
    isFetching: boolean;
    /**
     * Wait this long before painting the bar. Suppresses flicker for
     * fast fetches (~80% of cache misses come back in <100ms when the
     * day is already in the prefetch window).
     */
    delayMs?: number;
}

/**
 * Thin indeterminate progress bar that sits at the top of the Gantt
 * grid (just below the page-level controls header). Renders only when
 * at least one day request has been in-flight for longer than
 * `delayMs`. Pure CSS animation, no layout cost — uses the
 * `indeterminate-bar` keyframe defined in `app.css`.
 */
export default function GanttFetchingBar({
    isFetching,
    delayMs = 200,
}: Props) {
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        if (!isFetching) {
            setVisible(false);
            return;
        }
        const id = window.setTimeout(() => setVisible(true), delayMs);
        return () => window.clearTimeout(id);
    }, [isFetching, delayMs]);

    if (!visible) return null;

    return (
        <div
            aria-hidden
            className="pointer-events-none absolute top-0 inset-x-0 z-50 h-0.5 overflow-hidden bg-primary/10"
        >
            <div
                className="h-full w-1/4 bg-primary"
                style={{
                    animation: 'indeterminate-bar 1.4s ease-in-out infinite',
                }}
            />
        </div>
    );
}
