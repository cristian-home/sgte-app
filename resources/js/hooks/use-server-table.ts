import { getCoreRowModel, useReactTable } from '@tanstack/react-table';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { buildSortParam, buildUrl, parseSortParam } from '@/lib/query-params';

import type {
    ColumnDef,
    SortingState,
    Table,
    VisibilityState,
} from '@tanstack/react-table';
import type { PaginatedData } from '@/types';

export interface UseServerTableOptions<TData> {
    /** Initial paginated data from Inertia props (SSR-compatible). */
    data: PaginatedData<TData>;

    /** TanStack column definitions. */
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    columns: ColumnDef<TData, any>[];

    /** Debounce delay in ms for search input. Default: 300. */
    debounceMs?: number;

    /** Default hidden columns. */
    initialColumnVisibility?: VisibilityState;

    /**
     * Forwarded to TanStack's `useReactTable({ meta })` so columns can
     * read shared context (e.g. backend-supplied options or row-action
     * callbacks) via `table.options.meta` without the index page
     * having to pass them as props to every cell.
     */
    meta?: unknown;
}

export interface UseServerTableReturn<TData> {
    /** The TanStack table instance, ready for rendering. */
    table: Table<TData>;

    /** Current paginated data (updates on each fetch). */
    paginatedData: PaginatedData<TData>;

    /** Current search term (controlled). */
    search: string;

    /** Setter for search term. */
    setSearch: (value: string) => void;

    /** Whether a fetch is in-flight. */
    loading: boolean;

    /** Navigate to a specific pagination URL (from pagination links). */
    onNavigate: (url: string) => void;

    /** Change per-page count. */
    onPerPageChange: (perPage: string) => void;

    /** Currently active faceted filters derived from URL params. */
    activeFilters: Record<string, string[]>;

    /** Set values for a specific filter (empty array removes it). */
    setFilter: (name: string, values: string[]) => void;

    /**
     * Set multiple filters in a single navigate call. Use this when a
     * UI control needs to update more than one filter at the same
     * time (e.g. a preset that sets both date_from and date_to).
     * Back-to-back `setFilter` calls race `currentParams` because
     * `currentParams` only refreshes after the previous fetch lands,
     * so this batched variant is the only safe path for multi-field
     * writes.
     */
    setFilters: (updates: Record<string, string[]>) => void;

    /** Clear all faceted filters (preserves search). */
    clearFilters: () => void;

    /**
     * Update rows in `paginatedData.data` in place. Useful when an XHR
     * action mutates a single row and the server returns the new state,
     * so we can merge it without re-fetching the entire page or going
     * through Inertia's full reload cycle.
     */
    mutateRow: (
        predicate: (row: TData) => boolean,
        updater: (row: TData) => TData,
    ) => void;
}

export function useServerTable<TData>({
    data: initialData,
    columns,
    debounceMs = 300,
    initialColumnVisibility = {},
    meta,
}: UseServerTableOptions<TData>): UseServerTableReturn<TData> {
    const [paginatedData, setPaginatedData] =
        useState<PaginatedData<TData>>(initialData);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        setPaginatedData(initialData);
    }, [initialData]);
    const [columnVisibility, setColumnVisibility] = useState<VisibilityState>(
        initialColumnVisibility,
    );

    // Derive base path and current params from the paginated response
    const basePath = paginatedData.path;
    const currentParams = useMemo(() => {
        const activeUrl = new URL(
            paginatedData.links.find((l) => l.active)?.url ??
                paginatedData.first_page_url,
            typeof window !== 'undefined'
                ? window.location.origin
                : 'http://localhost',
        );
        const params: Record<string, string> = {};
        activeUrl.searchParams.forEach((value, key) => {
            params[key] = value;
        });
        return params;
    }, [paginatedData.links, paginatedData.first_page_url]);

    const [search, setSearch] = useState(currentParams['filter[search]'] ?? '');
    const [sorting, setSorting] = useState<SortingState>(
        parseSortParam(currentParams['sort'] ?? ''),
    );

    // Derive active faceted filters from current URL params
    const activeFilters = useMemo(() => {
        const filters: Record<string, string[]> = {};
        for (const [key, value] of Object.entries(currentParams)) {
            const match = key.match(/^filter\[(.+)]$/);
            if (match && match[1] !== 'search' && value) {
                filters[match[1]] = value.split(',');
            }
        }
        return filters;
    }, [currentParams]);

    const debounceRef = useRef<ReturnType<typeof setTimeout> | undefined>(
        undefined,
    );
    const isFirstRender = useRef(true);
    const abortRef = useRef<AbortController | undefined>(undefined);

    const fetchData = useCallback(
        async (newParams: Record<string, string>) => {
            abortRef.current?.abort();
            const controller = new AbortController();
            abortRef.current = controller;

            const url = buildUrl(basePath, newParams);

            setLoading(true);
            try {
                const response = await fetch(url, {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                });
                if (!response.ok) return;
                const json = (await response.json()) as PaginatedData<TData>;
                setPaginatedData(json);

                // Sync browser URL without navigation (preserve Inertia's page state)
                history.replaceState(history.state, '', url);
            } catch (e) {
                if (e instanceof DOMException && e.name === 'AbortError')
                    return;
                throw e;
            } finally {
                if (!controller.signal.aborted) {
                    setLoading(false);
                }
            }
        },
        [basePath],
    );

    function navigate(overrides: Record<string, string | undefined>) {
        const merged: Record<string, string> = { ...currentParams };
        for (const [key, val] of Object.entries(overrides)) {
            if (val === undefined || val === '') {
                delete merged[key];
            } else {
                merged[key] = val;
            }
        }
        fetchData(merged);
    }

    // Debounced search
    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }
        clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => {
            navigate({
                'filter[search]': search || undefined,
                page: undefined,
            });
        }, debounceMs);
        return () => clearTimeout(debounceRef.current);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const onNavigate = useCallback(
        (url: string) => {
            const targetParams = new URL(url, window.location.origin)
                .searchParams;
            const merged: Record<string, string> = {};
            targetParams.forEach((value, key) => {
                merged[key] = value;
            });
            fetchData(merged);
        },
        [fetchData],
    );

    const onPerPageChange = useCallback(
        (perPage: string) => {
            navigate({ per_page: perPage, page: undefined });
        },
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [currentParams, fetchData],
    );

    const setFilter = useCallback(
        (name: string, values: string[]) => {
            navigate({
                [`filter[${name}]`]: values.length
                    ? values.join(',')
                    : undefined,
                page: undefined,
            });
        },
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [currentParams, fetchData],
    );

    const setFilters = useCallback(
        (updates: Record<string, string[]>) => {
            const overrides: Record<string, string | undefined> = {
                page: undefined,
            };
            for (const [name, values] of Object.entries(updates)) {
                overrides[`filter[${name}]`] = values.length
                    ? values.join(',')
                    : undefined;
            }
            navigate(overrides);
        },
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [currentParams, fetchData],
    );

    const mutateRow = useCallback(
        (
            predicate: (row: TData) => boolean,
            updater: (row: TData) => TData,
        ) => {
            setPaginatedData((prev) => ({
                ...prev,
                data: prev.data.map((r) => (predicate(r) ? updater(r) : r)),
            }));
        },
        [],
    );

    const clearFilters = useCallback(() => {
        const overrides: Record<string, string | undefined> = {
            page: undefined,
        };
        for (const key of Object.keys(currentParams)) {
            if (key.startsWith('filter[') && key !== 'filter[search]') {
                overrides[key] = undefined;
            }
        }
        navigate(overrides);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [currentParams, fetchData]);

    // eslint-disable-next-line react-hooks/incompatible-library
    const table = useReactTable({
        data: paginatedData.data,
        columns,
        state: { sorting, columnVisibility },
        onSortingChange: (updater) => {
            const next =
                typeof updater === 'function' ? updater(sorting) : updater;
            setSorting(next);
            navigate({
                sort: buildSortParam(next),
                page: undefined,
            });
        },
        onColumnVisibilityChange: setColumnVisibility,
        manualSorting: true,
        manualPagination: true,
        getCoreRowModel: getCoreRowModel(),
        pageCount: paginatedData.last_page,
        meta: meta as Record<string, unknown> | undefined,
    });

    return {
        table,
        paginatedData,
        search,
        setSearch,
        loading,
        onNavigate,
        onPerPageChange,
        activeFilters,
        setFilter,
        setFilters,
        clearFilters,
        mutateRow,
    };
}
