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
    /**
     * Width of the sticky Vehículo sidebar in px. Subtracted from
     * clientWidth when locating the "centered day" so the calc
     * reflects the TIMELINE midpoint (visually centered), not the
     * scroller midpoint (which would be 56 px off, occasionally
     * landing one column past where the user actually looks — see
     * the URL-off-by-one report after pressing Hoy late at night).
     */
    sidebarPx: number;
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
    sidebarPx,
    debounceMs = 400,
}: UseGanttScrollOptions): void {
    const lastReportedRef = useRef<Ymd | null>(null);
    const timeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    // Mirror sidebarPx in a ref so the bound scroll listener always
    // reads the latest value without re-attaching when the breakpoint
    // crosses (mobile↔desktop), which would race with in-flight scrolls.
    const sidebarPxRef = useRef(sidebarPx);
    useEffect(() => {
        sidebarPxRef.current = sidebarPx;
    }, [sidebarPx]);

    useEffect(() => {
        const scroller = scrollerRef.current;
        if (!scroller) return;

        function compute() {
            if (!scroller) return;
            // Center of the TIMELINE area (past the sticky sidebar) in
            // canvas coordinates. The naive scroller midpoint
            // (scrollLeft + clientWidth/2) is biased sidebarPx/2 to the
            // left because the sidebar covers the leftmost slice of the
            // viewport.
            const visibleTimelineWidth =
                scroller.clientWidth - sidebarPxRef.current;
            const center = scroller.scrollLeft + visibleTimelineWidth / 2;
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
