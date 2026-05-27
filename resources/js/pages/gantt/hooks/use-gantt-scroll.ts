import { useEffect, useRef } from 'react';
import {
    addDays,
    dayOffset,
    instantToPxFromEpoch,
    PX_PER_DAY,
    type Ymd,
} from '../utils/coordinates';

interface UseGanttScrollOptions {
    /** Ref to the horizontally-scrolling container. */
    scrollerRef: React.RefObject<HTMLElement | null>;
    /** The epoch date the timeline is anchored to. */
    epoch: Ymd;
    /**
     * Fires when the centered date changes after a quiet period. The page
     * uses this to update the URL (and to invoke `ensureDay` on the
     * cache so the centered day is guaranteed loaded).
     */
    onCenterDateChange: (date: Ymd) => void;
    /** Milliseconds to wait without scroll motion before firing. */
    debounceMs?: number;
}

/**
 * Watches `scroller.scrollLeft` and reports the "centered day" — the
 * calendar day whose midpoint lies closest to the visual center of the
 * scroll viewport — debounced so we don't fire on every animation frame.
 *
 * Stays consistent during a seamless edge slide because the
 * compensation (setEpoch + scrollLeft adjustment) is atomic before
 * paint: when this listener fires, both values are already reconciled
 * and the computed date matches the one the user was looking at.
 */
export function useGanttScroll({
    scrollerRef,
    epoch,
    onCenterDateChange,
    debounceMs = 400,
}: UseGanttScrollOptions): void {
    const lastReportedRef = useRef<Ymd | null>(null);
    const timeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        const scroller = scrollerRef.current;
        if (!scroller) return;

        function compute() {
            if (!scroller) return;
            const center = scroller.scrollLeft + scroller.clientWidth / 2;
            const dayIndex = Math.round(center / PX_PER_DAY - 0.5);
            const date = addDays(epoch, dayIndex);
            if (date !== lastReportedRef.current) {
                lastReportedRef.current = date;
                onCenterDateChange(date);
            }
        }

        function onScroll() {
            if (timeoutRef.current) {
                clearTimeout(timeoutRef.current);
            }
            timeoutRef.current = setTimeout(compute, debounceMs);
        }

        scroller.addEventListener('scroll', onScroll, { passive: true });
        // Fire once at mount so the initial center is reported.
        compute();

        return () => {
            scroller.removeEventListener('scroll', onScroll);
            if (timeoutRef.current) {
                clearTimeout(timeoutRef.current);
            }
        };
    }, [scrollerRef, epoch, onCenterDateChange, debounceMs]);
}

/**
 * Compute the pixel position to `scrollTo` so that `date` lands at the
 * horizontal center of `clientWidth`. Used by the "jump to date"
 * controls and the SSR-anchored initial scroll.
 */
export function scrollLeftForDateCenter(
    date: Ymd,
    epoch: Ymd,
    clientWidth: number,
): number {
    const dayStartPx = dayOffset(date, epoch) * PX_PER_DAY;
    const dayCenterPx = dayStartPx + PX_PER_DAY / 2;
    return Math.max(0, Math.round(dayCenterPx - clientWidth / 2));
}

/**
 * Compute the pixel position to `scrollTo` so a specific instant
 * lands at the horizontal center of `clientWidth`. Used by the "Hoy"
 * shortcut to center the viewport on the Now indicator (not on
 * noon of today, which is what scrollLeftForDateCenter would do).
 */
export function scrollLeftForInstantCenter(
    iso: string,
    tz: string,
    epoch: Ymd,
    clientWidth: number,
): number {
    const px = instantToPxFromEpoch(iso, tz, epoch);
    return Math.max(0, Math.round(px - clientWidth / 2));
}
