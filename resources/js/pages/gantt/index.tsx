import { Head, usePage } from '@inertiajs/react';
import { useCallback, useMemo, useRef, useState } from 'react';
import { index as ganttIndex } from '@/actions/App/Http/Controllers/GanttController';
import { type MunicipalityOption } from '@/components/municipality-combobox';
import AppLayout from '@/layouts/app-layout';
import GanttHeader from './components/gantt-header';
import GanttLegend from './components/gantt-legend';
import HourlyGrid, {
    defaultEpochFor,
    defaultNumDays,
} from './components/hourly-grid';
import { useGanttDays } from './hooks/use-gantt-days';
import { addDays, dayOffset, type Ymd } from './utils/coordinates';

import type { BreadcrumbItem } from '@/types';
import type { DayStatus, Service, Vehicle } from '@/types/models';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Planificador Gantt', href: ganttIndex().url },
];

// Days to add at each edge expansion. Half the window — keeps the
// before/after symmetry intuitive (right shift moves us ~half-window
// into the future content, left shift symmetric).
const EDGE_SHIFT_DAYS = 30;
// How long the lock waits before releasing. With normal network the
// visible days resolve in <300ms; this is mostly a safety in case a
// request stalls. After the lock releases, bars keep appearing async
// via the regular ensureDay path while the GanttFetchingBar continues
// to communicate that something's still loading.
const SWAP_RELEASE_MS = 600;

interface Props {
    vehicles: Vehicle[];
    services: Service[];
    dayStatus: DayStatus | null;
    municipalities: MunicipalityOption[];
    date: string;
    municipalityId: number | null;
    canCreateServices: boolean;
}

export default function GanttIndex({
    vehicles,
    services,
    dayStatus,
    municipalities,
    date,
    municipalityId,
    canCreateServices,
}: Props) {
    const sharedConfig = usePage().props.config as
        | { operation_tz?: string }
        | undefined;
    const operationTz = sharedConfig?.operation_tz ?? 'America/Bogota';

    // SSR date becomes the initial center of the timeline. The epoch
    // is `defaultEpochFor` days earlier (half-window). Both can change
    // at runtime via edge expansion or out-of-range date picker jump.
    const [epoch, setEpoch] = useState<Ymd>(() => defaultEpochFor(date));
    const numDays = useMemo(() => defaultNumDays(), []);

    const initialDay = useMemo(
        () => ({ date: date as Ymd, services, dayStatus }),
        [date, services, dayStatus],
    );

    // Per-day cache lives at the page so the floating
    // ExecutedDayBanner above the timeline can react to the centered
    // day's status. HourlyGrid reads it for the inline DaySeparator
    // badges and the per-day Executed gate at click time.
    const initialSeed = useMemo(() => [initialDay], [initialDay]);
    const { cache, ensureDay, isFetching } = useGanttDays({
        seed: initialSeed,
    });

    // `centerDate` mirrors what the timeline reports as "currently
    // centered". Used to keep the header date picker and URL in sync.
    const [centerDate, setCenterDate] = useState<Ymd>(date as Ymd);

    // Non-null while a swap is in progress — gates re-entry, drives
    // visual lock state, and tells useGanttScroll to ignore scroll
    // events emitted by the imperative scrollLeft adjustment.
    const [isExpanding, setIsExpanding] = useState<
        'left' | 'right' | 'jump' | null
    >(null);

    // Pending anchor date for after an epoch swap. Bumping this with a
    // new value triggers a useLayoutEffect inside HourlyGrid that
    // recomputes scrollLeft from (anchorDate, epoch, scroller.clientWidth)
    // BEFORE the browser paints, so the user sees a clean swap.
    const [anchorDate, setAnchorDate] = useState<Ymd | null>(null);

    // jumpToDate (exposed from HourlyGrid via onMount) — used for the
    // in-range path of date-picker / Hoy / prev/next buttons.
    const jumpToDateRef = useRef<((date: Ymd) => void) | null>(null);

    const handleCenterDateChange = useCallback((newDate: Ymd) => {
        setCenterDate(newDate);
        const url = new URL(window.location.href);
        url.searchParams.set('date', newDate);
        window.history.replaceState(window.history.state, '', url.toString());
    }, []);

    /**
     * Coordinate an epoch swap. Common path for both edge expansion
     * and out-of-range date picker jumps.
     *
     *   1. Flip isExpanding ON (locks scroll, dims canvas, pauses URL
     *      sync).
     *   2. Set new epoch + anchorDate. React batches into one render.
     *      A useLayoutEffect inside HourlyGrid recomputes scrollLeft
     *      from (anchorDate, new epoch, scroller.clientWidth) before
     *      paint — no visible jump.
     *   3. Wait `SWAP_RELEASE_MS` for visible-day fetches to land.
     *      The GanttFetchingBar continues to communicate any leftover.
     *   4. Flip isExpanding OFF — scroll unlocks, URL sync resumes.
     */
    const performSwap = useCallback(
        async (kind: 'left' | 'right' | 'jump', newEpoch: Ymd, anchor: Ymd) => {
            setIsExpanding(kind);
            setEpoch(newEpoch);
            setAnchorDate(anchor);
            setCenterDate(anchor); // optimistic — header date picker reflects target
            // Update URL directly here. useGanttScroll is paused during
            // the swap so its debounced listener won't fire; without
            // this explicit update the URL stays at the pre-swap date.
            const url = new URL(window.location.href);
            url.searchParams.set('date', anchor);
            window.history.replaceState(
                window.history.state,
                '',
                url.toString(),
            );
            await new Promise((resolve) =>
                window.setTimeout(resolve, SWAP_RELEASE_MS),
            );
            setIsExpanding(null);
            setAnchorDate(null);
        },
        [],
    );

    const handleEdgeExpansion = useCallback(
        (side: 'left' | 'right', liveCenterDate: Ymd) => {
            if (isExpanding) return;
            const shiftDays =
                side === 'right' ? EDGE_SHIFT_DAYS : -EDGE_SHIFT_DAYS;
            const newEpoch = addDays(epoch, shiftDays);
            // Anchor: the LIVE centered date from the scroller — the
            // state `centerDate` may lag behind by a frame because of
            // useGanttScroll's debounce.
            performSwap(side, newEpoch, liveCenterDate);
        },
        [isExpanding, epoch, performSwap],
    );

    const handleJumpToDate = useCallback(
        (target: Ymd) => {
            if (isExpanding) return;
            const offset = dayOffset(target, epoch);
            const inRange = offset >= 0 && offset < numDays;
            if (inRange) {
                // In-range: smooth scroll (current behavior).
                jumpToDateRef.current?.(target);
                return;
            }
            // Out-of-range: re-center the window on the picked date.
            const newEpoch = addDays(target, -Math.floor(numDays / 2));
            performSwap('jump', newEpoch, target);
        },
        [isExpanding, epoch, numDays, performSwap],
    );

    const dimmedDuringSwap = isExpanding !== null;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Planificador Gantt" />
            <div
                className="flex flex-col gap-4 p-4"
                style={{ height: 'calc(100svh - 5rem)' }}
            >
                <GanttHeader
                    date={centerDate}
                    municipalityId={municipalityId}
                    municipalities={municipalities}
                    canCreateServices={canCreateServices}
                    onJumpToDate={handleJumpToDate}
                />
                <GanttLegend />
                <div
                    className={
                        'min-h-0 flex-1 overflow-hidden rounded-lg border transition-opacity duration-150 ' +
                        (dimmedDuringSwap ? 'cursor-wait opacity-90' : '')
                    }
                >
                    <HourlyGrid
                        vehicles={vehicles}
                        initialDay={initialDay}
                        epoch={epoch}
                        operationTz={operationTz}
                        canCreateServices={canCreateServices}
                        cache={cache}
                        ensureDay={ensureDay}
                        isFetching={isFetching}
                        numDays={numDays}
                        onCenterDateChange={handleCenterDateChange}
                        onMount={(jump) => {
                            jumpToDateRef.current = jump;
                        }}
                        isExpanding={isExpanding}
                        onRequestEpochShift={handleEdgeExpansion}
                        anchorDate={anchorDate}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
