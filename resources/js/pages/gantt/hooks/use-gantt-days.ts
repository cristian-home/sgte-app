import { useCallback, useEffect, useRef, useState } from 'react';
import { index as ganttIndex } from '@/actions/App/Http/Controllers/GanttController';
import { addDays, type Ymd } from '../utils/coordinates';

import type { Service } from '@/types/models';

export type DayStatus = 'loading' | 'ready' | 'error';

export interface DayEntry {
    status: DayStatus;
    services: Service[];
    error?: string;
}

export type DayCache = Record<Ymd, DayEntry | undefined>;

export interface UseGanttDaysOptions {
    /**
     * Days already populated by SSR / Inertia props — used to seed the
     * cache so the first paint never shows a "loading" placeholder for
     * the day the user landed on.
     */
    seed?: Array<{ date: Ymd; services: Service[] }>;
}

export interface UseGanttDaysReturn {
    cache: DayCache;
    /**
     * Ensure the given date is in the cache. Fetches if missing; idempotent
     * for in-flight requests. Also queues prefetch for date ± 1 so a
     * left/right scroll has its neighbours ready.
     */
    ensureDay: (date: Ymd) => void;
    /** True when at least one day request is currently in flight. */
    isFetching: boolean;
}

/**
 * Client-side cache + fetcher for per-day Gantt services. The page mounts
 * with the SSR-loaded day in `seed`; every other day is pulled on demand
 * via `GET /gantt?date=Y` with `Accept: application/json` (the
 * controller has a `wantsJson()` branch returning
 * `{ date, services, dayStatus }`).
 *
 * The hook is intentionally barebones: no LRU eviction in MVP. If memory
 * pressure becomes a problem (operator scrolls a full year), we can cap
 * the cache size from outside without changing the surface.
 */
export function useGanttDays({ seed }: UseGanttDaysOptions = {}): UseGanttDaysReturn {
    const [cache, setCache] = useState<DayCache>(() => {
        const initial: DayCache = {};
        for (const { date, services } of seed ?? []) {
            initial[date] = { status: 'ready', services };
        }
        return initial;
    });
    // Mirror the latest cache snapshot into a ref so `doFetch` can guard
    // synchronously — `setCache` updaters are async and would let
    // duplicate fetches slip through under back-to-back ensureDay
    // calls. Sync runs in an effect (NOT during render) per
    // react-hooks/refs.
    const cacheRef = useRef<DayCache>(cache);
    useEffect(() => {
        cacheRef.current = cache;
    }, [cache]);

    // Track in-flight aborts so we don't leak fetches when the component
    // unmounts mid-scroll.
    const abortersRef = useRef<Map<Ymd, AbortController>>(new Map());

    useEffect(() => {
        const aborters = abortersRef.current;
        return () => {
            for (const aborter of aborters.values()) {
                aborter.abort();
            }
            aborters.clear();
        };
    }, []);

    /**
     * Fire a single fetch for `date`. Idempotent against in-flight,
     * ready, or errored entries.
     */
    const doFetch = useCallback((date: Ymd) => {
        if (cacheRef.current[date]) {
            return;
        }
        const loadingEntry: DayEntry = { status: 'loading', services: [] };
        cacheRef.current = { ...cacheRef.current, [date]: loadingEntry };
        setCache((prev) => ({ ...prev, [date]: loadingEntry }));

        const aborter = new AbortController();
        abortersRef.current.set(date, aborter);

        const url = ganttIndex({ query: { date } }).url;
        fetch(url, {
            headers: { Accept: 'application/json' },
            signal: aborter.signal,
        })
            .then((r) => {
                if (!r.ok) {
                    throw new Error(`HTTP ${r.status}`);
                }
                return r.json() as Promise<{ date: Ymd; services: Service[] }>;
            })
            .then((json) => {
                abortersRef.current.delete(date);
                const readyEntry: DayEntry = {
                    status: 'ready',
                    services: json.services,
                };
                cacheRef.current = { ...cacheRef.current, [json.date]: readyEntry };
                setCache((prev) => ({ ...prev, [json.date]: readyEntry }));
            })
            .catch((err) => {
                if (err instanceof DOMException && err.name === 'AbortError') {
                    return;
                }
                abortersRef.current.delete(date);
                const errorEntry: DayEntry = {
                    status: 'error',
                    services: [],
                    error: err instanceof Error ? err.message : String(err),
                };
                cacheRef.current = { ...cacheRef.current, [date]: errorEntry };
                setCache((prev) => ({ ...prev, [date]: errorEntry }));
            });
    }, []);

    const ensureDay = useCallback(
        (date: Ymd) => {
            doFetch(date);
            // Background prefetch the adjacent days so a left/right
            // gesture has the data already. Microtask defers the
            // dispatch one tick so it doesn't compete with the main
            // fetch for the network slot.
            queueMicrotask(() => {
                doFetch(addDays(date, 1));
                doFetch(addDays(date, -1));
            });
        },
        [doFetch],
    );

    // Cheap O(n) on cache keys but n stays small in practice (<= 90
    // distinct days even after a full year of scrolling).
    const isFetching = Object.values(cache).some(
        (entry) => entry?.status === 'loading',
    );

    return { cache, ensureDay, isFetching };
}
