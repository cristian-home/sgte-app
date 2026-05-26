import { router } from '@inertiajs/react';
import { useVirtualizer } from '@tanstack/react-virtual';
import { useCallback, useEffect, useMemo, useRef } from 'react';
import {
    create as servicesCreate,
    edit as servicesEdit,
} from '@/actions/App/Http/Controllers/ServiceController';
import { cn } from '@/lib/utils';
import { computeVehicleDocStatus, HOUR_LABELS } from '../gantt-utils';
import { useGanttDays } from '../hooks/use-gantt-days';
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
import DayLoadingOverlay from './day-loading-overlay';
import DaySeparator from './day-separator';
import NowIndicator from './now-indicator';
import ServiceBar from './service-bar';
import VehicleSidebarItem from './vehicle-sidebar-item';

import type { Service, Vehicle } from '@/types/models';

interface InitialDay {
    date: Ymd;
    services: Service[];
}

interface HourlyGridProps {
    vehicles: Vehicle[];
    initialDay: InitialDay;
    /**
     * Anchor date for the continuous timeline; pixel 0 = 00:00 of
     * `epoch` in operation TZ. The page passes a stable epoch (e.g.
     * 6 months before today) so dayOffset() values stay reasonable.
     */
    epoch: Ymd;
    operationTz: string;
    canCreateServices: boolean;
    isExecuted: boolean;
    /** Total days the virtualizer can show (centered roughly on hoy). */
    numDays: number;
    /** Called when the centered day changes (for URL sync). */
    onCenterDateChange: (date: Ymd) => void;
    /**
     * Exposes a jumpToDate function back to the page so date controls
     * in the header can drive horizontal scroll.
     */
    onMount?: (jumpToDate: (date: Ymd) => void) => void;
}

const SIDEBAR_PX = 112; // w-28
const ROW_HEIGHT_PX = 36; // matches existing minHeight
const HEADER_HEIGHT_PX = 48; // day banner + hour labels strip

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
    isExecuted,
    numDays,
    onCenterDateChange,
    onMount,
}: HourlyGridProps) {
    const scrollerRef = useRef<HTMLDivElement | null>(null);
    const { cache, ensureDay } = useGanttDays({
        seed: [initialDay],
    });

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
        for (const [date, entry] of Object.entries(cache)) {
            if (!entry || entry.status !== 'ready') continue;
            const byVehicle = new Map<number, Service[]>();
            for (const s of entry.services) {
                const list = byVehicle.get(s.vehicle_id) ?? [];
                list.push(s);
                byVehicle.set(s.vehicle_id, list);
            }
            map.set(date as Ymd, byVehicle);
        }
        return map;
    }, [cache]);

    // URL sync via debounce-on-scroll.
    useGanttScroll({
        scrollerRef,
        epoch,
        onCenterDateChange,
        debounceMs: 400,
    });

    // Expose jumpToDate to the page so the header controls can drive
    // horizontal scroll. The page sees this in its first render via
    // the onMount callback.
    const jumpToDate = useCallback(
        (date: Ymd) => {
            const scroller = scrollerRef.current;
            if (!scroller) return;
            const left = scrollLeftForDateCenter(
                date,
                epoch,
                scroller.clientWidth - SIDEBAR_PX,
            );
            scroller.scrollTo({ left, behavior: 'smooth' });
        },
        [epoch],
    );

    // Mount callback identity may change every page render — read it
    // through a ref so the scroller ref-callback below stays stable.
    const onMountRef = useRef(onMount);
    useEffect(() => {
        onMountRef.current = onMount;
    }, [onMount]);

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
                        initialDay.date,
                        epoch,
                        node.clientWidth - SIDEBAR_PX,
                    );
                    node.scrollLeft = left;
                });
                onMountRef.current?.(jumpToDate);
            }
        },
        [epoch, initialDay.date, jumpToDate],
    );

    function handleEmptyCellClick(
        vehicle: Vehicle,
        e: React.MouseEvent<HTMLDivElement>,
    ) {
        const status = vehicleStatuses[vehicle.id];
        if (status?.isBlocked || isExecuted || !canCreateServices) return;
        const target = e.currentTarget;
        const rect = target.getBoundingClientRect();
        // clientX − rect.left is the click position INSIDE the timeline
        // region of the row (the sidebar has its own rect). Add the
        // scroller's scrollLeft minus the sidebar offset to get the
        // absolute pixel on the timeline canvas.
        const absPx = e.clientX - rect.left;
        const { date, timeHHMM } = pixelToDateTime(absPx, epoch);
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
    const bodyHeightPx = vehicles.length * ROW_HEIGHT_PX;

    return (
        <div ref={setScroller} className="size-full overflow-auto">
            {/* Outer canvas: sidebar (sticky) + timeline canvas side by side. */}
            <div
                className="relative"
                style={{ width: SIDEBAR_PX + totalTimelineWidth }}
            >
                {/* Header strip: sticky top. Sidebar corner z-30, timeline header z-20. */}
                <div
                    className="sticky top-0 z-20 flex border-b bg-muted/50"
                    style={{ height: HEADER_HEIGHT_PX }}
                >
                    <div
                        className="sticky left-0 z-30 flex shrink-0 items-center border-r bg-background px-2"
                        style={{ width: SIDEBAR_PX }}
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
                                    <DaySeparator
                                        date={date}
                                        leftPx={0}
                                        widthPx={PX_PER_DAY}
                                        isToday={isToday}
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
                    const clickable =
                        canCreateServices && !status?.isBlocked && !isExecuted;
                    return (
                        <div
                            key={vehicle.id}
                            className={cn(
                                'flex border-b',
                                status?.isBlocked &&
                                    'bg-neutral-100 dark:bg-neutral-800/50',
                            )}
                        >
                            <div
                                className="sticky left-0 z-10 flex shrink-0 items-center border-r bg-background"
                                style={{
                                    width: SIDEBAR_PX,
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
                                        const pos = serviceBarAbsolutePosition(
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
                                            />
                                        );
                                    });
                                })}
                            </div>
                        </div>
                    );
                })}

                {/* Per-day loading overlays. Rendered at the canvas level
                    so a single overlay covers ALL vehicle rows for that
                    day. The 200ms delay inside the component hides them
                    for fast fetches (~80% case). */}
                {visibleDays.map((item) => {
                    const date = addDays(epoch, item.index);
                    const entry = cache[date];
                    if (entry?.status === 'ready' || entry?.status === 'error') {
                        return null;
                    }
                    return (
                        <DayLoadingOverlay
                            key={`loading-${item.key}`}
                            leftPx={SIDEBAR_PX + item.start}
                            widthPx={PX_PER_DAY}
                            topPx={HEADER_HEIGHT_PX}
                            heightPx={bodyHeightPx}
                        />
                    );
                })}

                {/* NOW indicator spans the body height; sits over all rows.
                    `leftOffsetPx` accounts for the sticky sidebar that
                    pushes the timeline canvas SIDEBAR_PX to the right
                    of the canvas root — without it the red line lands
                    ~3h to the left of real "now". */}
                <NowIndicator
                    epoch={epoch}
                    operationTz={operationTz}
                    contentHeightPx={HEADER_HEIGHT_PX + bodyHeightPx}
                    leftOffsetPx={SIDEBAR_PX}
                />

                {/* Reserve the canvas height so vertical scroll inside the
                    page wrapper is predictable even when no vehicles. */}
                {vehicles.length === 0 && (
                    <div className="flex items-center justify-center py-12 text-sm text-muted-foreground">
                        No hay vehículos activos para mostrar.
                    </div>
                )}
            </div>
        </div>
    );
}

/** Convenience re-export for the page-level epoch computation. */
export function defaultEpochFor(today: Ymd, halfWindow = 182): Ymd {
    // The epoch is the LEFT-most day the timeline can show. So if
    // today is centered with `halfWindow` days on each side, the
    // epoch is `halfWindow` days BEFORE today.
    return addDays(today, -halfWindow);
}

export function defaultNumDays(halfWindow = 182): number {
    return halfWindow * 2 + 1;
}

export { dayOffset };
