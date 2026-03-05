---
name: annual-calendar
type: feat
scope: services
status: pending
priority: high
created_date: 2026-03-05
completed_date:
srs_refs: ["REQ-001"]
migration_strategy: modify-existing
---

# Annual Calendar with Day Status Colors

## Description

Replace the current day-statuses index page stub with an interactive annual calendar view. The calendar displays 12 months in a grid, with each day color-coded by its operational status: black (no services), orange (projected — at least one open service), green (executed — all services closed and day locked). Users can click a month to expand a detailed day view, and click a day to navigate to the services for that date. This is the primary navigation entry point for dispatchers to access daily operations.

## Acceptance Criteria

- [ ] AC-1: WHEN the user navigates to `/day-statuses` THEN a 12-month annual calendar grid MUST be displayed for the current year.
- [ ] AC-2: WHEN a day has no `DayStatus` record THEN it MUST be displayed with a neutral/black indicator (no services).
- [ ] AC-3: WHEN a day has a `DayStatus` with `status = projected` THEN it MUST be displayed with an orange indicator.
- [ ] AC-4: WHEN a day has a `DayStatus` with `status = executed` THEN it MUST be displayed with a green indicator.
- [ ] AC-5: WHEN the user clicks a month in the annual view THEN the view MUST expand or navigate to show a detailed monthly view with individual day cells, each color-coded and showing the service count for that day.
- [ ] AC-6: WHEN the user clicks a day in the monthly view THEN the browser MUST navigate to the services index filtered by that date (`/services?filter[service_date]={date}`).
- [ ] AC-7: WHEN the user clicks the previous/next year navigation arrows THEN the calendar MUST update to show the selected year's data.
- [ ] AC-8: WHEN the calendar is rendered THEN today's date MUST be visually highlighted (distinct border or ring) regardless of its status color.
- [ ] AC-9: WHEN a user with the `driver` role attempts to access `/day-statuses` THEN access MUST be denied (403).

## Technical Specification

### Data Model

No new tables or columns. The calendar reads from the existing `day_statuses` table and aggregates service counts from the `services` table.

### Enums

No new enums. Uses existing `DayStatusEnum` (`projected`, `executed`).

### Routes

No new routes. The existing resource route `GET /day-statuses` (index) is repurposed to serve the calendar view. A `year` query parameter is added for year navigation.

| Method | URI | Controller Action | Middleware | Name |
|--------|-----|-------------------|------------|------|
| GET | /day-statuses?year=2026 | DayStatusController@index | auth, verified | day-statuses.index |

### Permissions

No new permissions. Uses existing `VIEW_DAY_SUMMARY` permission to gate access.

### Pages

| Page | Component Path | Description |
|------|---------------|-------------|
| Calendar Index | `resources/js/pages/day-statuses/index.tsx` | Annual calendar view (replaces current stub) |
| Month Detail | Inline or modal within index | Expanded month view with day cells |

## Migration Strategy

- **modify-existing**: No database changes. The controller index method is updated to return structured data, and the frontend index page is replaced with the calendar component.

## Tasks

### Backend

- [ ] Task 1: Update `DayStatusController@index` to return calendar-optimized data
  - Gate check: `Gate::authorize(Permission::VIEW_DAY_SUMMARY->value)`
  - Accept `year` query parameter (default: current year). Validate it is a 4-digit integer between 2020 and 2099.
  - Query all `DayStatus` records for the year: `DayStatus::whereYear('date', $year)->get(['id', 'date', 'status', 'executor_id', 'executed_at'])`
  - Query service counts per date for the year: `Service::whereYear('service_date', $year)->whereNull('deleted_at')->selectRaw('service_date, count(*) as total, sum(case when service_status = ? then 1 else 0 end) as open_count', ['open'])->groupBy('service_date')->get()` — returns date, total services, and open service count
  - Transform data into a keyed map for efficient frontend lookup:
    ```php
    $dayStatuses = $dayStatuses->keyBy(fn ($ds) => $ds->date->format('Y-m-d'));
    $serviceCounts = $serviceCounts->keyBy('service_date');
    ```
  - Pass to Inertia: `dayStatuses` (keyed collection), `serviceCounts` (keyed collection), `year` (integer)
  - Follow existing controller patterns for Inertia rendering

- [ ] Task 2: Update `DayStatusController@index` to eager-load executor for tooltip display
  - Add `->with('executor:id,name')` to the DayStatus query so the frontend can show "Ejecutado por {name}" on hover for executed days
  - Only include executor data for executed day statuses to minimize payload

### Frontend

- [ ] Task 3: Create the annual calendar grid component at `resources/js/components/calendar/annual-calendar.tsx`
  - **Props interface:**
    ```
    {
      year: number
      dayStatuses: Record<string, { id: number; date: string; status: string; executor?: { id: number; name: string }; executed_at: string | null }>
      serviceCounts: Record<string, { service_date: string; total: number; open_count: number }>
      onMonthClick: (month: number) => void
      onYearChange: (year: number) => void
    }
    ```
  - **Layout:** 4 columns x 3 rows grid (`grid-cols-4 gap-4`) displaying 12 month cards
  - Each month card displays:
    - Month name (Spanish locale, capitalized — use `date-fns/locale/es` with `format(date, 'MMMM', { locale: es })`)
    - A mini calendar grid (7 columns for weekdays, rows for weeks)
    - Each day cell is a small colored dot or square:
      - No DayStatus record → `bg-neutral-800` (black/dark)
      - `status = projected` → `bg-orange-500`
      - `status = executed` → `bg-green-500`
      - Today → additional `ring-2 ring-primary` highlight
    - Summary below month name: total services count for the month (sum of serviceCounts for all days in month)
  - Year navigation: `ChevronLeft` and `ChevronRight` icons from lucide-react flanking the year number
  - Year change triggers Inertia visit to `day-statuses.index` with `year` query parameter: `router.get(route, { year: newYear }, { preserveState: true })`
  - Use `date-fns` functions: `startOfMonth`, `endOfMonth`, `eachDayOfInterval`, `getDay`, `format`, `isSameDay`, `isToday`
  - Use `useMemo` to pre-compute month data arrays and avoid re-renders
  - Follow Tailwind CSS v4 conventions and existing component patterns

- [ ] Task 4: Create the monthly detail view component at `resources/js/components/calendar/month-detail.tsx`
  - **Props interface:**
    ```
    {
      year: number
      month: number  // 0-indexed (0 = January)
      dayStatuses: Record<string, ...>  // same as annual calendar
      serviceCounts: Record<string, ...>
      onDayClick: (date: string) => void
      onClose: () => void
    }
    ```
  - **Layout:** Full-width card/dialog showing the expanded month
  - Header: Month name + Year, close button (X icon)
  - Grid: 7 columns (Lun, Mar, Mié, Jue, Vie, Sáb, Dom — Spanish weekday abbreviations, week starts on Monday)
  - Each day cell displays:
    - Day number
    - Color indicator (same scheme: black/orange/green)
    - Service count badge (e.g., "5 servicios" or just the number)
    - For executed days: small check icon or "Ejecutado" tooltip on hover showing executor name and timestamp
  - Day cells are clickable — triggers `onDayClick(dateString)` which navigates to services filtered by date
  - Days outside the current month (padding) are displayed dimmed/muted
  - Today highlighted with `ring-2 ring-primary`
  - Use `Card` component from shadcn for the container
  - Use `Tooltip` component for executed day hover info

- [ ] Task 5: Replace `resources/js/pages/day-statuses/index.tsx` with the calendar implementation
  - **Props** (from controller): `{ dayStatuses, serviceCounts, year }`
  - **State:** `selectedMonth: number | null` (null = annual view, number = month detail)
  - **Breadcrumbs:** `[{ title: 'Calendario', href: dayStatusesIndex().url }]`
  - **Page title:** `<Head title="Calendario" />`
  - **Layout:** `AppLayout` wrapper
  - **Rendering logic:**
    - When `selectedMonth` is null: render `AnnualCalendar` component
    - When `selectedMonth` is set: render `MonthDetail` component alongside or replacing the annual view
  - **Month click handler:** `setSelectedMonth(month)` to expand month detail
  - **Day click handler:** Navigate using Inertia router to services index with date filter: `router.get(servicesIndex().url, { 'filter[service_date]': dateString })`
  - **Year change handler:** Inertia visit with new year parameter, reset selectedMonth to null
  - **Permission:** Page is gated by `VIEW_DAY_SUMMARY` in the controller; no additional frontend permission check needed on the page itself (controller handles 403)
  - Follow `resources/js/pages/vehicles/index.tsx` as layout convention reference

- [ ] Task 6: Update the sidebar navigation label for day-statuses
  - In `resources/js/components/app-sidebar.tsx` (or wherever the sidebar items are defined):
    - Change the label for the day-statuses link from "Estados del Día" (or current label) to "Calendario" to match the new calendar view
    - Keep the same route (`dayStatusesIndex()`)
    - Use `Calendar` icon from lucide-react (or keep existing icon if appropriate)

- [ ] Task 7: Add Spanish locale configuration for date-fns
  - Create `resources/js/lib/date-utils.ts` (or add to existing utils file if one exists)
  - Export a pre-configured `formatDate` helper that uses Spanish locale:
    ```
    import { format } from 'date-fns';
    import { es } from 'date-fns/locale';
    export const formatDateEs = (date: Date, pattern: string) => format(date, pattern, { locale: es });
    ```
  - Export Spanish weekday abbreviations array: `['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom']`
  - Export Spanish month names array for display consistency
  - This utility is reusable by future components (Gantt, Day Summary)

### Tests

- [ ] Task 8: Create `tests/Feature/Http/Controllers/DayStatusCalendarTest.php` using `php artisan make:test --pest`
  - Test: index returns `dayStatuses` and `serviceCounts` props for the current year
  - Test: index with `?year=2025` returns data filtered to 2025
  - Test: index with invalid year parameter falls back to current year or returns validation error
  - Test: `dayStatuses` are keyed by date string (format `Y-m-d`)
  - Test: `serviceCounts` include `total` and `open_count` per date
  - Test: executed day statuses include `executor` relationship data
  - Test: user without `VIEW_DAY_SUMMARY` permission gets 403
  - Test: user with `VIEW_DAY_SUMMARY` permission can access the page
  - Use factories to create DayStatus records and Services for specific dates
  - Follow `tests/Feature/Http/Controllers/VehicleControllerTest.php` as convention reference

- [ ] Task 9: Update existing DayStatus controller tests (if any) to account for the new index response format
  - Check `tests/Feature/Http/Controllers/DayStatusControllerTest.php` for existing tests
  - Update assertions to match new prop structure (`dayStatuses` as keyed collection, `serviceCounts`, `year`)
  - Ensure no regressions in existing test coverage

## Dependencies

- `day-status-logic` (pending) — provides auto-created DayStatus records when services are created, and the executed state. Without this, the calendar would only show black (no data) days.

## Notes

- **No external calendar library is used.** The calendar is a custom React component built with date-fns (already installed v4.1.0) and Tailwind CSS. This avoids adding a heavy dependency like FullCalendar for what is essentially a colored grid.
- **Day click navigates to services index** filtered by date. When the `daily-gantt` and `day-summary` requirements are implemented, the navigation target will be updated to link to the Gantt or Summary page instead. For now, the services index with `filter[service_date]` provides immediate value.
- **The annual view is the default.** Monthly detail is shown inline (expanded card or modal) when a month is clicked, with a close button to return to the annual grid. This avoids separate page loads for drill-down.
- **Service counts are aggregated server-side** with a single GROUP BY query to avoid N+1 issues. The frontend receives pre-computed totals per date.
- **Week starts on Monday** (Colombian/European convention) in the monthly detail grid.
- **The `day-statuses/create`, `edit`, `show` pages** are auto-generated stubs that will NOT be modified in this requirement. Direct CRUD of DayStatus records is not a user workflow — day statuses are managed automatically (via observer) and through the "Execute Day" action.
- **Performance:** For a full year, the maximum number of DayStatus records is 366. Service counts are also at most 366 rows. This data volume is trivial and does not require pagination or lazy loading.
- **Dark mode support:** The color scheme (black/orange/green) works naturally in both light and dark themes. The "black" indicator uses `bg-neutral-800 dark:bg-neutral-700` for visibility. Orange and green use Tailwind's standard palette.
