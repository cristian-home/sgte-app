# ADR-003: Reusable DataTable component with TanStack React Table

**Status:** Accepted
**Date:** 2026-03-02

## Context

Multiple modules in the system (services, vehicles, drivers, contracts, etc.) require tabular listings with common features: search, server-side pagination, faceted filters, column sorting, and column visibility. Implementing this individually in each page would create significant duplication.

A component system is needed that:
1. Supports server-side pagination and search via Inertia.
2. Allows view-configurable faceted filters.
3. Supports column sorting synchronized with the backend.
4. Is extensible to different models without modifying the base component.

## Decision

### 1. Component architecture

A component system was created under `resources/js/components/data-table/` based on TanStack React Table, with separation of concerns:

| Component | Responsibility |
|---|---|
| `data-table.tsx` | Main container. Accepts server-side mode (with `PaginatedData`) or client-side mode (with `data[]` + `columns[]`). |
| `data-table-toolbar.tsx` | Top toolbar: search field, faceted filters, column visibility, and actions. |
| `data-table-pagination.tsx` | Bottom pagination: records-per-page selector, page navigation, result count. |
| `data-table-column-header.tsx` | Column header with sort indicators (ascending/descending). |
| `data-table-faceted-filter.tsx` | Multi-select popover filter with integrated search. |
| `data-table-view-options.tsx` | Dropdown to dynamically show/hide columns. |
| `data-table-row-actions.tsx` | Per-row action dropdown menu (edit, delete). |

### 2. `useServerTable` hook

The hook `resources/js/hooks/use-server-table.ts` was created. It encapsulates all server-side state logic:

- Syncs search, filters, sorting, and pagination with URL parameters via Inertia's `router.visit()`.
- Configurable debounce on search (300ms by default).
- Handles the `loading` state during Inertia navigations.
- Returns the TanStack Table instance along with handlers ready to pass to the `DataTable` component.

### 3. Per-module usage pattern

Each module defines its table in two files inside its page directory:

- **`columns.tsx`** — Defines the TanStack `ColumnDef[]` with `meta.label` for labels, `DataTableColumnHeader` for sorting, and `DataTableRowActions` for actions.
- **`index.tsx`** — Inertia page that receives `PaginatedData` from the backend, initializes `useServerTable()`, defines `FilterDefinition[]`, and renders `<DataTable>`.

### 4. Backend integration

The component integrates with Spatie QueryBuilder in the controller:

- `filter[search]` — Search parameter (processed by the `SearchesDatabase` trait or Scout).
- `filter[field]` — Exact faceted filters.
- `sort` / `-sort` — Ascending/descending sort.
- `per_page` — Records per page.
- `page` — Page number.

The `PaginatedData<T>` shape maps directly to Laravel's `->paginate()->withQueryString()` response.

### 5. Shared types

Types are defined in:
- `resources/js/types/pagination.ts` — `PaginatedData<T>`, `PaginationLink`
- `resources/js/types/data-table.ts` — `FilterOption`, `FilterDefinition`

## Consequences

**Positive:**
- Adding a new listing only requires defining columns and filters; search, pagination, and sorting behavior come for free.
- Visual and functional consistency across all modules.
- Server-side by default: pagination and search do not load all records on the client.
- Two-way URL synchronization: filters and page are shareable via URL.

**Negative:**
- Coupling to TanStack React Table as a UI dependency.
- Adding functionality to a specific table may require understanding the TanStack API.
- The `useServerTable` hook assumes Laravel's pagination structure; another format would require adaptation.

**Key files:**
- `resources/js/components/data-table/` — Table system components
- `resources/js/components/ui/table.tsx` — Base HTML primitives
- `resources/js/hooks/use-server-table.ts` — Server-side state hook
- `resources/js/types/pagination.ts` — Pagination types
- `resources/js/types/data-table.ts` — Filter types
- `resources/js/pages/services/index.tsx` — Implementation example
- `resources/js/pages/services/columns.tsx` — Column definition example
