import { router } from '@inertiajs/react';
import { useVirtualizer } from '@tanstack/react-virtual';
import {
    useCallback,
    useEffect,
    useLayoutEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import {
    create as servicesCreate,
    edit as servicesEdit,
} from '@/actions/App/Http/Controllers/ServiceController';
import { cn } from '@/lib/utils';
import { computeVehicleDocStatus, HOUR_LABELS } from '../gantt-utils';
import {
    scrollLeftForDateCenter,
    useGanttScroll,
} from '../hooks/use-gantt-scroll';
import {
    addDays,
    dayOffset,
    pixelToDateTime,
    PX_PER_DAY,
    PX_PER_HOUR,
    serviceBarAbsolutePosition,
    type Ymd,
} from '../utils/coordinates';
import DaySeparator from './day-separator';
import GanttFetchingBar from './gantt-fetching-bar';
import NowIndicator from './now-indicator';
import ServiceBar from './service-bar';
import VehicleSidebarItem from './vehicle-sidebar-item';

import type { Service, Vehicle } from '@/types/models';
import type { DayCache } from '../hooks/use-gantt-days';

interface InitialDay {
    date: Ymd;
    services: Service[];
}

interface HourlyGridProps {
    vehicles: Vehicle[];
    initialDay: InitialDay;
    /**
     * Anchor date for the continuous timeline; pixel 0 = 00:00 of
     * `epoch` in operation TZ. Mutable across renders — when the page
     * shifts the epoch (edge expansion or out-of-range date pick), the
     * grid re-anchors `scrollLeft` synchronously in a useLayoutEffect
     * so the user sees a clean swap, not a 30-day jump for a frame.
     */
    epoch: Ymd;
    operationTz: string;
    canCreateServices: boolean;
    /**
     * Per-day cache owned by the page (via useGanttDays). The grid
     * reads services + dayStatus from it; `isExecuted` is no longer a
     * global prop — service creation is gated per-day at click time.
     */
    cache: DayCache;
    ensureDay: (date: Ymd) => void;
    isFetching: boolean;
    /** Total days the virtualizer can show (centered roughly on hoy). */
    numDays: number;
    /** Called when the centered day changes (for URL sync). */
    onCenterDateChange: (date: Ymd) => void;
    /**
     * Exposes a jumpToDate function back to the page so date controls
     * in the header can drive horizontal scroll.
     */
    onMount?: (jumpToDate: (date: Ymd) => void) => void;
    /**
     * Non-null while the page is performing an epoch swap. The grid
     * uses this to (a) lock scroll via a defensive listener, (b) dim
     * the canvas + cursor-wait, and (c) suppress the URL sync from
     * `useGanttScroll` so the imperative scrollLeft adjustment doesn't
     * propagate a bogus centered date.
     */
    isExpanding?: 'left' | 'right' | 'jump' | null;
    /**
     * Fires when the scroll position has settled within the trigger
     * threshold of either edge. `centerDateAtTrigger` is the date the
     * grid believes the viewport is centered on RIGHT NOW (computed
     * from live `scrollLeft` + current `epoch`) — we pass it explicit
     * because the page's `centerDate` state may lag behind by a frame
     * due to debounced URL-sync.
     */
    onRequestEpochShift?: (
        side: 'left' | 'right',
        centerDateAtTrigger: Ymd,
    ) => void;
    /**
     * Date to re-anchor the viewport on after a state update. When
     * non-null, the grid sets `scrollLeft` so that this date lands at
     * the horizontal center of the timeline area — using the CURRENT
     * value of `epoch` and the live `scroller.clientWidth`. The page
     * bumps this together with a new `epoch` to coordinate an
     * out-of-range jump or edge expansion; clears it back to null
     * once the swap is settled.
     */
    anchorDate?: Ymd | null;
}

const SIDEBAR_PX_MOBILE = 96; // w-24 — plate + 2 abbreviated badges
const SIDEBAR_PX_DESKTOP = 112; // w-28 — original, fits full badge text
const SIDEBAR_BREAKPOINT_PX = 640; // Tailwind `sm`
const ROW_HEIGHT_PX = 36; // matches existing minHeight
const HEADER_HEIGHT_PX = 48; // day banner + hour labels strip

/**
 * Sidebar width follows the Tailwind `sm` breakpoint: narrower on
 * mobile so the timeline reclaims ~30px of viewport, full width on
 * tablet+ where horizontal real estate is plentiful. SSR-safe — picks
 * the desktop value first, then upgrades after hydration if the
 * viewport reports otherwise.
 */
function useSidebarWidthPx(): number {
    const [px, setPx] = useState<number>(SIDEBAR_PX_DESKTOP);

    useEffect(() => {
        if (typeof window === 'undefined') return;
        const mq = window.matchMedia(
            `(min-width: ${SIDEBAR_BREAKPOINT_PX}px)`,
        );
        const update = () =>
            setPx(mq.matches ? SIDEBAR_PX_DESKTOP : SIDEBAR_PX_MOBILE);
        update();
        mq.addEventListener('change', update);
        return () => mq.removeEventListener('change', update);
    }, []);

    return px;
}

/**
 * Pre-baked grid-line background: 23 dim borders per day + 1 brighter
 * midnight. Lines sit at the START of each block (positions 0, 38, 76…
 * for hours, 0, 912, 1824… for days) so they align pixel-perfect with
 * the header cells, which use `border-l` (border on the LEFT of each
 * cell). Putting them at the end would offset everything by 1px.
 */
const GRID_BG_STYLE: React.CSSProperties = {
    backgroundImage: `repeating-linear-gradient(
        to right,
        rgba(120, 120, 120, 0.18) 0 1px,
        transparent 1px ${PX_PER_HOUR}px
    ), repeating-linear-gradient(
        to right,
        rgba(120, 120, 120, 0.45) 0 1px,
        transparent 1px ${PX_PER_DAY}px
    )`,
    backgroundSize: `${PX_PER_HOUR}px 100%, ${PX_PER_DAY}px 100%`,
};

export default function HourlyGrid({
    vehicles,
    initialDay,
    epoch,
    operationTz,
    canCreateServices,
    cache,
    ensureDay,
    isFetching,
    numDays,
    onCenterDateChange,
    onMount,
    isExpanding = null,
    onRequestEpochShift,
    anchorDate = null,
}: HourlyGridProps) {
    const scrollerRef = useRef<HTMLDivElement | null>(null);
    const sidebarPx = useSidebarWidthPx();
    // Service ids that have been mounted at least once during this
    // page lifetime. Used to gate the ServiceBar fade-in animation so
    // bars re-entering the viewport via the horizontal virtualizer
    // don't flash back to opacity 0 (which read as a distracting
    // "blink" while scrolling). First mount runs the animation; every
    // subsequent mount of the same id skips it.
    const seenServiceIdsRef = useRef<Set<number>>(new Set());
    const markServiceSeen = useCallback((id: number) => {
        seenServiceIdsRef.current.add(id);
    }, []);
    // Mirror in a ref so stable callbacks (jumpToDate, setScroller) can
    // read the latest viewport-driven value without re-binding on every
    // breakpoint change — re-binding the scroller ref races with the
    // useLayoutEffect that anchors scrollLeft after an epoch swap.
    const sidebarPxRef = useRef(sidebarPx);
    useEffect(() => {
        sidebarPxRef.current = sidebarPx;
    }, [sidebarPx]);

    const today = useMemo(
        () =>
            new Intl.DateTimeFormat('en-CA', { timeZone: operationTz }).format(
                new Date(),
            ),
        [operationTz],
    );

    const vehicleStatuses = useMemo(() => {
        const map: Record<
            number,
            { isBlocked: boolean; hasWarning: boolean; expiredDocs: string[] }
        > = {};
        for (const v of vehicles) {
            map[v.id] = computeVehicleDocStatus(v, today);
        }
        return map;
    }, [vehicles, today]);

    const getScrollElement = useCallback(() => scrollerRef.current, []);
    const estimateSize = useCallback(() => PX_PER_DAY, []);

    const virtualizer = useVirtualizer({
        horizontal: true,
        count: numDays,
        // Subtract sidebar so the viewport that drives "which days are
        // visible" matches the actual timeline region.
        getScrollElement,
        estimateSize,
        overscan: 1,
    });

    const visibleDays = virtualizer.getVirtualItems();

    // Trigger fetches for visible (+ overscan) days. ensureDay is
    // idempotent — second-and-later calls for the same date are no-ops.
    // Runs in an effect (NOT during render) so we don't trip React's
    // "setState during render" guard (#301 loop).
    useEffect(() => {
        for (const item of visibleDays) {
            ensureDay(addDays(epoch, item.index));
        }
    }, [visibleDays, epoch, ensureDay]);

    // Index services by date+vehicle for O(1) lookup inside each row.
    const servicesByDateVehicle = useMemo(() => {
        const map = new Map<Ymd, Map<number, Service[]>>();
        for (const [date, entry] of cache.entries()) {
            if (entry.status !== 'ready') continue;
            const byVehicle = new Map<number, Service[]>();
            for (const s of entry.services) {
                const list = byVehicle.get(s.vehicle_id) ?? [];
                list.push(s);
                byVehicle.set(s.vehicle_id, list);
            }
            map.set(date, byVehicle);
        }
        return map;
    }, [cache]);

    // URL sync via debounce-on-scroll. Paused during an epoch swap so
    // the imperative scrollLeft adjustment doesn't propagate a bogus
    // centered date to the URL.
    useGanttScroll({
        scrollerRef,
        epoch,
        onCenterDateChange,
        debounceMs: 400,
        pauseScrollSync: isExpanding !== null,
    });

    // Edge watcher: when scrolling settles within 5% of either edge,
    // ask the page to expand the window. The page gates re-entry with
    // its own `isExpanding` state, but we also guard here so we don't
    // spam the callback inside a single settle.
    const lastEdgeFireRef = useRef<'left' | 'right' | null>(null);
    useEffect(() => {
        const scroller = scrollerRef.current;
        if (!scroller || !onRequestEpochShift) return;

        let timeoutId: ReturnType<typeof setTimeout> | null = null;
        function checkEdge() {
            if (!scroller) return;
            if (isExpanding) return;
            const { scrollLeft, scrollWidth, clientWidth } = scroller;
            const rightEdge = scrollLeft + clientWidth;
            const rightThreshold = scrollWidth * 0.95;
            const leftThreshold = scrollWidth * 0.05;

            // Compute the date the viewport is centered on RIGHT NOW
            // (independent of the page's debounced centerDate state).
            const centerPx = scrollLeft + clientWidth / 2;
            const dayIndex = Math.round(centerPx / PX_PER_DAY - 0.5);
            const liveCenterDate = addDays(epochRef.current, dayIndex);

            if (rightEdge > rightThreshold) {
                if (lastEdgeFireRef.current === 'right') return;
                lastEdgeFireRef.current = 'right';
                onRequestEpochShift?.('right', liveCenterDate);
            } else if (scrollLeft < leftThreshold) {
                if (lastEdgeFireRef.current === 'left') return;
                lastEdgeFireRef.current = 'left';
                onRequestEpochShift?.('left', liveCenterDate);
            } else {
                // Out of edge zones — reset so a future re-entry fires again.
                lastEdgeFireRef.current = null;
            }
        }

        function onScroll() {
            if (timeoutId) clearTimeout(timeoutId);
            timeoutId = setTimeout(checkEdge, 400);
        }

        scroller.addEventListener('scroll', onScroll, { passive: true });
        return () => {
            scroller.removeEventListener('scroll', onScroll);
            if (timeoutId) clearTimeout(timeoutId);
        };
    }, [isExpanding, onRequestEpochShift]);

    // Defensive scroll lock during expansion: snap scrollLeft back to
    // wherever it was when the lock engaged. Prevents the user from
    // continuing to scroll into the void while the swap is in flight.
    const lockedScrollLeftRef = useRef<number | null>(null);
    useEffect(() => {
        const scroller = scrollerRef.current;
        if (!scroller) return;
        if (isExpanding) {
            lockedScrollLeftRef.current = scroller.scrollLeft;
            const snap = () => {
                if (lockedScrollLeftRef.current !== null) {
                    scroller.scrollLeft = lockedScrollLeftRef.current;
                }
            };
            scroller.addEventListener('scroll', snap);
            return () => {
                scroller.removeEventListener('scroll', snap);
                lockedScrollLeftRef.current = null;
            };
        }
    }, [isExpanding]);

    // Imperative re-anchor of scrollLeft after the page swaps the
    // epoch. useLayoutEffect runs synchronously after DOM mutation but
    // BEFORE the browser paints, so the user never sees the "old
    // scrollLeft + new epoch" frame. Math lives here (not in the
    // page) so we can use the LIVE scroller.clientWidth — the page's
    // captured-once value drifts on resize.
    useLayoutEffect(() => {
        if (!anchorDate) return;
        const scroller = scrollerRef.current;
        if (!scroller) return;
        const offset = dayOffset(anchorDate, epoch);
        const dayCenterPx = (offset + 0.5) * PX_PER_DAY;
        const timelineWidth = scroller.clientWidth - sidebarPxRef.current;
        const left = Math.max(0, Math.round(dayCenterPx - timelineWidth / 2));
        scroller.scrollLeft = left;
    }, [anchorDate, epoch]);

    // Expose jumpToDate to the page so the header controls can drive
    // horizontal scroll. The page sees this in its first render via
    // the onMount callback. Reads epoch through a ref so the function
    // identity stays stable across renders — see comment on
    // setScroller below for why that matters.
    const jumpToDate = useCallback((date: Ymd) => {
        const scroller = scrollerRef.current;
        if (!scroller) return;
        const left = scrollLeftForDateCenter(
            date,
            epochRef.current,
            scroller.clientWidth - sidebarPxRef.current,
        );
        scroller.scrollTo({ left, behavior: 'smooth' });
    }, []);

    // Mount callback identity may change every page render — read it
    // through a ref so the scroller ref-callback below stays stable.
    const onMountRef = useRef(onMount);
    useEffect(() => {
        onMountRef.current = onMount;
    }, [onMount]);
    // Same trick for the live epoch: setScroller MUST stay stable
    // (no `epoch` in deps) otherwise React unbind+rebinds the ref on
    // every epoch change, which races with our useLayoutEffect anchor.
    const epochRef = useRef(epoch);
    useEffect(() => {
        epochRef.current = epoch;
    }, [epoch]);
    const initialDayRef = useRef(initialDay);
    useEffect(() => {
        initialDayRef.current = initialDay;
    }, [initialDay]);

    // Initial scroll-anchor: when the scroller is first mounted, center
    // on the initial day. Wraps in a microtask so layout is settled.
    const didInitialScrollRef = useRef(false);
    const setScroller = useCallback(
        (node: HTMLDivElement | null) => {
            scrollerRef.current = node;
            if (node && !didInitialScrollRef.current) {
                didInitialScrollRef.current = true;
                queueMicrotask(() => {
                    const left = scrollLeftForDateCenter(
                        initialDayRef.current.date,
                        epochRef.current,
                        node.clientWidth - sidebarPxRef.current,
                    );
                    node.scrollLeft = left;
                });
                onMountRef.current?.(jumpToDate);
            }
        },
        [jumpToDate],
    );

    function handleEmptyCellClick(
        vehicle: Vehicle,
        e: React.MouseEvent<HTMLDivElement>,
    ) {
        const status = vehicleStatuses[vehicle.id];
        if (status?.isBlocked || !canCreateServices) return;
        const target = e.currentTarget;
        const rect = target.getBoundingClientRect();
        // clientX − rect.left is the click position INSIDE the timeline
        // region of the row (the sidebar has its own rect). Add the
        // scroller's scrollLeft minus the sidebar offset to get the
        // absolute pixel on the timeline canvas.
        const absPx = e.clientX - rect.left;
        const { date, timeHHMM } = pixelToDateTime(absPx, epoch);
        // Per-day gate: services can't be created on a day already
        // marked Executed. Checked here at click time (rather than as a
        // row-level prop) because a single row spans the full 61-day
        // canvas, in which any subset of days may be Executed.
        if (cache.get(date)?.dayStatus?.status === 'executed') return;
        router.get(servicesCreate().url, {
            vehicle_id: vehicle.id,
            planned_start_time: timeHHMM,
            service_date: date,
        });
    }

    function handleServiceClick(serviceId: number) {
        router.get(servicesEdit(serviceId).url);
    }

    const totalTimelineWidth = numDays * PX_PER_DAY;

    return (
        <div className="relative size-full">
            {/* Top-of-grid fetching indicator. Lives OUTSIDE the
                scroller so it stays anchored horizontally across the
                visible width regardless of scroll position. */}
            <GanttFetchingBar isFetching={isFetching} />
            <div ref={setScroller} className="size-full overflow-auto">
                {/* Outer canvas: sidebar (sticky) + timeline canvas side by side. */}
                <div
                    className="relative"
                    style={{ width: sidebarPx + totalTimelineWidth }}
                >
                    {/* Header strip: sticky top. Sidebar corner z-30, timeline header z-20. */}
                    <div
                        className="sticky top-0 z-20 flex border-b bg-muted/50"
                        style={{ height: HEADER_HEIGHT_PX }}
                    >
                        <div
                            className="sticky left-0 z-30 flex shrink-0 items-center border-r bg-background px-2"
                            style={{ width: sidebarPx }}
                        >
                            <span className="text-xs font-medium text-muted-foreground">
                                Vehículo
                            </span>
                        </div>
                        <div
                            className="relative"
                            style={{ width: totalTimelineWidth }}
                        >
                            {visibleDays.map((item) => {
                                const date = addDays(epoch, item.index);
                                const isToday = date === today;
                                return (
                                    <div
                                        key={item.key}
                                        className="absolute inset-y-0"
                                        style={{
                                            left: item.start,
                                            width: PX_PER_DAY,
                                        }}
                                    >
                                        {/* Day-column header background.
                                            Separate from DaySeparator so
                                            the sticky label inside can
                                            shrink to content while the
                                            full-width muted strip stays
                                            put. */}
                                        <div
                                            className={cn(
                                                'absolute inset-x-0 top-0 h-6 border-x border-border',
                                                isToday
                                                    ? 'bg-primary/10'
                                                    : 'bg-muted/80',
                                            )}
                                        />
                                        <DaySeparator
                                            date={date}
                                            isToday={isToday}
                                            dayStatus={
                                                cache.get(date)?.dayStatus ??
                                                null
                                            }
                                            sidebarPx={sidebarPx}
                                        />
                                        {/* Hour ticks below the day banner. */}
                                        <div
                                            className="absolute inset-x-0 bottom-0 flex"
                                            style={{ top: 24 }}
                                        >
                                            {HOUR_LABELS.map((label) => (
                                                <div
                                                    key={label}
                                                    className="flex-1 border-l border-border/50 p-1 text-center text-[10px] text-muted-foreground"
                                                >
                                                    {label}
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    {/* Body rows. */}
                    {vehicles.map((vehicle) => {
                        const status = vehicleStatuses[vehicle.id];
                        // Cursor affordance at the row level reflects vehicle
                        // eligibility only. The per-day Executed gate fires
                        // inside `handleEmptyCellClick` because a single row
                        // spans many days with mixed statuses.
                        const clickable =
                            canCreateServices && !status?.isBlocked;
                        return (
                            <div
                                key={vehicle.id}
                                className={cn(
                                    'flex border-b',
                                    status?.isBlocked &&
                                        'bg-neutral-100 dark:bg-neutral-800/50',
                                )}
                                // `content-visibility: auto` lets the browser
                                // skip layout/paint/composite for rows that
                                // are off-screen vertically — effectively a
                                // free Y-virtualization. The intrinsic-size
                                // hint keeps the scroll height stable so the
                                // skip doesn't cause layout shifts when rows
                                // enter/exit the viewport. CSS expects width
                                // then height; the row is the timeline wide
                                // and one row-height tall.
                                style={{
                                    contentVisibility: 'auto',
                                    containIntrinsicSize: `${sidebarPx + totalTimelineWidth}px ${ROW_HEIGHT_PX}px`,
                                }}
                            >
                                <div
                                    className="sticky left-0 z-10 flex shrink-0 items-center border-r bg-background"
                                    style={{
                                        width: sidebarPx,
                                        height: ROW_HEIGHT_PX,
                                    }}
                                >
                                    <VehicleSidebarItem
                                        vehicle={vehicle}
                                        isBlocked={status?.isBlocked ?? false}
                                        hasWarning={status?.hasWarning ?? false}
                                        expiredDocs={status?.expiredDocs ?? []}
                                    />
                                </div>
                                <div
                                    className={cn(
                                        'relative',
                                        clickable
                                            ? 'cursor-cell'
                                            : 'cursor-default',
                                    )}
                                    style={{
                                        width: totalTimelineWidth,
                                        height: ROW_HEIGHT_PX,
                                        // contain: strict (= size + layout +
                                        // paint + style) lets the compositor
                                        // clip aggressively at this box and
                                        // avoids style/layout side-effects
                                        // bleeding out. Most noticeable on
                                        // mobile GPUs that tile the wide
                                        // background gradient.
                                        contain: 'strict',
                                        ...GRID_BG_STYLE,
                                    }}
                                    onClick={(e) =>
                                        handleEmptyCellClick(vehicle, e)
                                    }
                                >
                                    {visibleDays.map((item) => {
                                        const date = addDays(epoch, item.index);
                                        const services =
                                            servicesByDateVehicle
                                                .get(date)
                                                ?.get(vehicle.id) ?? [];
                                        return services.map((service) => {
                                            const pos =
                                                serviceBarAbsolutePosition(
                                                    service.planned_start_at,
                                                    service.planned_duration,
                                                    service.timezone,
                                                    epoch,
                                                );
                                            if (!pos) return null;
                                            return (
                                                <ServiceBar
                                                    key={service.id}
                                                    service={service}
                                                    position={{
                                                        left: pos.left,
                                                        width: pos.width,
                                                        unit: 'px',
                                                    }}
                                                    onClick={handleServiceClick}
                                                    animateOnMount={
                                                        !seenServiceIdsRef.current.has(
                                                            service.id,
                                                        )
                                                    }
                                                    onMounted={markServiceSeen}
                                                />
                                            );
                                        });
                                    })}
                                </div>
                            </div>
                        );
                    })}

                    {/* NOW indicator spans the body height; sits over all
                    rows. Only mounted when "today" falls within the
                    current window — otherwise its absolute `left`
                    would extend hundreds of thousands of px past the
                    canvas and balloon scrollWidth (regression caught
                    on /gantt?date=2022-09-01 where today was 1.36k
                    days from epoch and scrollWidth was 1.3M px).
                    `leftOffsetPx` accounts for the sticky sidebar. */}
                    {(() => {
                        const todayOffset = dayOffset(today, epoch);
                        if (todayOffset < 0 || todayOffset >= numDays) {
                            return null;
                        }
                        return (
                            <NowIndicator
                                epoch={epoch}
                                operationTz={operationTz}
                                topOffsetPx={HEADER_HEIGHT_PX}
                                leftOffsetPx={sidebarPx}
                            />
                        );
                    })()}

                    {/* Reserve the canvas height so vertical scroll inside the
                    page wrapper is predictable even when no vehicles. */}
                    {vehicles.length === 0 && (
                        <div className="flex items-center justify-center py-12 text-sm text-muted-foreground">
                            No hay vehículos activos para mostrar.
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

/**
 * Convenience re-export for the page-level epoch computation.
 *
 * The half-window of 30 yields a 61-day canvas (1 month each
 * direction). Tradeoff vs. the original 182 (365 days):
 *
 * - Canvas width 333k → 56k px. Comfortably within composite limits
 *   for desktop dGPUs, iPhone 8+, and most Android mid-tier (tiles
 *   cleanly; entry-level rarely falls back to CPU paint).
 * - "Continuous scroll" goes from 6 months each way to ~1 month.
 *   Further dates need a date-picker jump — the URL re-anchors the
 *   epoch automatically, so the UX is "scroll for context, jump for
 *   distance".
 */
export function defaultEpochFor(today: Ymd, halfWindow = 30): Ymd {
    return addDays(today, -halfWindow);
}

export function defaultNumDays(halfWindow = 30): number {
    return halfWindow * 2 + 1;
}

export { dayOffset };
