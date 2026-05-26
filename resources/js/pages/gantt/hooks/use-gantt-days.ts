import { useCallback, useEffect, useRef, useState } from 'react';
import { index as ganttIndex } from '@/actions/App/Http/Controllers/GanttController';
import { addDays, type Ymd } from '../utils/coordinates';

import type { DayStatus, Service } from '@/types/models';

export type DayFetchStatus = 'loading' | 'ready' | 'error';

export interface DayEntry {
    status: DayFetchStatus;
    services: Service[];
    /**
     * Operational day-status row for this date (Projected / Executed /
     * Blocked). Populated by both the SSR seed and the per-day JSON
     * fetch. Drives the per-day badge in DaySeparator and the
     * centered-day banner above the timeline.
     */
    dayStatus: DayStatus | null;
    error?: string;
}

export type DayCache = Map<Ymd, DayEntry>;

export interface UseGanttDaysOptions {
    /**
     * Days already populated by SSR / Inertia props — used to seed the
     * cache so the first paint never shows a "loading" placeholder for
     * the day the user landed on.
     */
    seed?: Array<{
        date: Ymd;
        services: Service[];
        dayStatus: DayStatus | null;
    }>;
    /**
     * LRU cap. When the cache exceeds this count, the least-recently-
     * touched entries are evicted. With ~30 services/day average and
     * 24 vehicles, 90 days ≈ 65 KB of JS heap — generous yet bounded.
     */
    maxCachedDays?: number;
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
    /**
     * True when every date in `dates` has `status === 'ready'`. Used by
     * the page-level edge-expansion handler to know when it's safe to
     * release the scroll lock — the user has actual content to look at.
     */
    areAllReady: (dates: Ymd[]) => boolean;
}

/**
 * Client-side cache + fetcher for per-day Gantt services. The page mounts
 * with the SSR-loaded day in `seed`; every other day is pulled on demand
 * via `GET /gantt?date=Y` with `Accept: application/json` (the
 * controller has a `wantsJson()` branch returning
 * `{ date, services, dayStatus }`).
 *
 * Cache uses a `Map` so insertion order doubles as the LRU ordering: the
 * first key is the oldest, the last is the most-recently touched. Each
 * `ensureDay` and each successful fetch re-inserts the date at the end,
 * so frequent access keeps an entry "young". When `size > maxCachedDays`
 * we drop from the front until back within budget.
 */
export function useGanttDays({
    seed,
    maxCachedDays = 90,
}: UseGanttDaysOptions = {}): UseGanttDaysReturn {
    const [cache, setCache] = useState<DayCache>(() => {
        const initial: DayCache = new Map();
        for (const { date, services, dayStatus } of seed ?? []) {
            initial.set(date, { status: 'ready', services, dayStatus });
        }
        return initial;
    });
    // Mirror the latest cache snapshot into a ref so `doFetch` can guard
    // synchronously — `setCache` updaters are async and would let
    // duplicate fetches slip through under back-to-back ensureDay
    // calls. Sync runs in an effect (NOT during render).
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
     * Apply an immutable mutation to the cache + propagate the LRU
     * eviction. Returns the new Map. The caller is responsible for
     * calling `setCache` with the result.
     */
    const buildNextCache = useCallback(
        (prev: DayCache, date: Ymd, entry: DayEntry): DayCache => {
            const next = new Map(prev);
            // Touch — delete first so re-set puts the entry at the end.
            next.delete(date);
            next.set(date, entry);
            // LRU eviction. Map iteration is in insertion order so the
            // FIRST keys are the oldest.
            while (next.size > maxCachedDays) {
                const oldest = next.keys().next().value;
                if (!oldest) break;
                next.delete(oldest);
            }
            return next;
        },
        [maxCachedDays],
    );

    /** Fire a single fetch for `date`. Idempotent. */
    const doFetch = useCallback(
        (date: Ymd) => {
            if (cacheRef.current.has(date)) {
                // Touch the LRU so frequently-revisited days don't get
                // evicted just because they were inserted early.
                const existing = cacheRef.current.get(date);
                if (existing) {
                    cacheRef.current = buildNextCache(
                        cacheRef.current,
                        date,
                        existing,
                    );
                    setCache(cacheRef.current);
                }
                return;
            }
            const loadingEntry: DayEntry = {
                status: 'loading',
                services: [],
                dayStatus: null,
            };
            cacheRef.current = buildNextCache(
                cacheRef.current,
                date,
                loadingEntry,
            );
            setCache(cacheRef.current);

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
                    return r.json() as Promise<{
                        date: Ymd;
                        services: Service[];
                        dayStatus: DayStatus | null;
                    }>;
                })
                .then((json) => {
                    abortersRef.current.delete(date);
                    const readyEntry: DayEntry = {
                        status: 'ready',
                        services: json.services,
                        dayStatus: json.dayStatus,
                    };
                    cacheRef.current = buildNextCache(
                        cacheRef.current,
                        json.date,
                        readyEntry,
                    );
                    setCache(cacheRef.current);
                })
                .catch((err) => {
                    if (
                        err instanceof DOMException &&
                        err.name === 'AbortError'
                    ) {
                        return;
                    }
                    abortersRef.current.delete(date);
                    const errorEntry: DayEntry = {
                        status: 'error',
                        services: [],
                        dayStatus: null,
                        error: err instanceof Error ? err.message : String(err),
                    };
                    cacheRef.current = buildNextCache(
                        cacheRef.current,
                        date,
                        errorEntry,
                    );
                    setCache(cacheRef.current);
                });
        },
        [buildNextCache],
    );

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

    // Cheap O(n) on cache entries — n is capped at maxCachedDays.
    let isFetching = false;
    for (const entry of cache.values()) {
        if (entry.status === 'loading') {
            isFetching = true;
            break;
        }
    }

    const areAllReady = useCallback((dates: Ymd[]) => {
        for (const date of dates) {
            if (cacheRef.current.get(date)?.status !== 'ready') {
                return false;
            }
        }
        return true;
    }, []);

    return { cache, ensureDay, isFetching, areAllReady };
}
