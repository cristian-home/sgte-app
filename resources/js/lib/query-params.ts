import type { SortingState } from '@tanstack/react-table';

/**
 * Parse a Spatie QueryBuilder sort string into TanStack SortingState.
 * Example: "-service_date,origin" → [{ id: 'service_date', desc: true }, { id: 'origin', desc: false }]
 */
export function parseSortParam(sort: string): SortingState {
    if (!sort) return [];
    return sort.split(',').map((s) => ({
        id: s.startsWith('-') ? s.slice(1) : s,
        desc: s.startsWith('-'),
    }));
}

/**
 * Build a Spatie QueryBuilder sort string from TanStack SortingState.
 * Example: [{ id: 'service_date', desc: true }] → "-service_date"
 */
export function buildSortParam(sorting: SortingState): string | undefined {
    if (sorting.length === 0) return undefined;
    return sorting.map((s) => (s.desc ? `-${s.id}` : s.id)).join(',');
}

/**
 * Build a URL from a base path and query params.
 */
export function buildUrl(
    basePath: string,
    params: Record<string, string>,
): string {
    const searchParams = new URLSearchParams(params);
    const qs = searchParams.toString();
    return qs ? `${basePath}?${qs}` : basePath;
}
