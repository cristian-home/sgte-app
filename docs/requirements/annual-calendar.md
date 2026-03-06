---
name: annual-calendar
type: feat
scope: services
status: completed
priority: high
created_date: 2026-03-05
completed_date: 2026-03-05
srs_refs: ["REQ-001"]
migration_strategy: modify-existing
---

# Annual Calendar with Day Status Colors

## Description

Replace the current day-statuses index page stub with an interactive annual calendar view. The calendar displays 12 months in a grid, with each day color-coded by its operational status: black (no services), orange (projected — at least one open service), green (executed — all services closed and day locked). Navigation is entirely path-based: `/day-statuses/{year}` for the annual view, `/day-statuses/{year}/{month}` for the month detail. Users can click a month to drill into a detailed monthly view with prev/next month arrows, and click a day to load the day's services inline below the calendar. This is the primary navigation entry point for dispatchers to access daily operations.

## Acceptance Criteria

- [x] AC-1: WHEN the user navigates to `/day-statuses` THEN the browser MUST redirect to `/day-statuses/{currentYear}` and display a 12-month annual calendar grid.
- [x] AC-2: WHEN a day has no `DayStatus` record THEN it MUST be displayed with a neutral/black indicator (no services).
- [x] AC-3: WHEN a day has a `DayStatus` with `status = projected` THEN it MUST be displayed with an orange indicator.
- [x] AC-4: WHEN a day has a `DayStatus` with `status = executed` THEN it MUST be displayed with a green indicator.
- [x] AC-5: WHEN the user clicks a month in the annual view THEN the browser MUST navigate to `/day-statuses/{year}/{month}` showing a detailed monthly view with individual day cells, each color-coded and showing the service count for that day.
- [x] AC-6: WHEN the user clicks a day in the monthly view THEN the day's services MUST be loaded inline below the calendar (URL becomes `/day-statuses/{year}/{month}?selectedDay={day}`). The browser MUST NOT navigate away from the calendar page.
- [x] AC-7: WHEN the user clicks the previous/next year navigation arrows THEN the calendar MUST navigate to `/day-statuses/{newYear}`.
- [x] AC-7b: WHEN the user clicks the previous/next month arrows in the month detail THEN the browser MUST navigate to the adjacent month URL, handling year boundaries (e.g., prev from January navigates to `/day-statuses/{year-1}/12`).
- [x] AC-8: WHEN the calendar is rendered THEN today's date MUST be visually highlighted (distinct border or ring) regardless of its status color.
- [x] AC-9: WHEN a user with the `driver` role attempts to access `/day-statuses` THEN access MUST be denied (403).

## Technical Specification

### Data Model

No new tables or columns. The calendar reads from the existing `day_statuses` table and aggregates service counts from the `services` table.

### Enums

No new enums. Uses existing `DayStatusEnum` (`projected`, `executed`).

### Routes

Two new explicit routes are added before the existing resource route. The resource `index` action now redirects to the calendar. Route constraints enforce valid years (2020-2099) and months (1-12).

| Method | URI | Controller Action | Middleware | Name |
|--------|-----|-------------------|------------|------|
| GET | /day-statuses | DayStatusController@index | auth, verified | day-statuses.index (redirects to calendar) |
| GET | /day-statuses/{year} | DayStatusController@calendar | auth, verified | day-statuses.calendar |
| GET | /day-statuses/{year}/{month}?selectedDay={day} | DayStatusController@calendarMonth | auth, verified | day-statuses.calendar-month |

### Permissions

No new permissions. Uses existing `VIEW_DAY_SUMMARY` permission to gate access.

### Pages

| Page | Component Path | Description |
|------|---------------|-------------|
| Calendar Index | `resources/js/pages/day-statuses/index.tsx` | Annual calendar view (replaces current stub) |
| Month Detail | Inline within index | Expanded month view with prev/next arrows and day cells |
| Day Services | Inline within index | Service table loaded below the calendar when a day is clicked |

## Migration Strategy

- **modify-existing**: No database changes. Two new controller methods (`calendar`, `calendarMonth`) serve path-based URLs. The existing `index` method redirects. The frontend index page is replaced with the calendar component.

## Tasks

### Backend

- [x] Task 1: Add path-based routes and refactor `DayStatusController`
  - Add `GET /day-statuses/{year}` (`calendar`) and `GET /day-statuses/{year}/{month}` (`calendarMonth`) routes with `where` constraints before the resource route
  - Refactor `index()` to redirect to `day-statuses.calendar` with current year
  - `calendar(int $year)`: Gate check, query DayStatus + ServiceCounts for the year, render `day-statuses/index` with `month: null`
  - `calendarMonth(Request, int $year, int $month)`: Same year data plus optional `selectedDay` query param. When present, constructs the full date from year/month/day and queries services with `contract:id,contract_number`, `vehicle:id,plate`, `driver:id,first_name,first_lastname` relations, ordered by `planned_start_time`
  - Shared `calendarData(int $year)` private method extracts common queries (DayStatus + ServiceCounts keyed by date)
  - Eager-load `executor:id,name` on DayStatus for tooltip display

### Frontend

- [x] Task 3: Create the annual calendar grid component at `resources/js/components/calendar/annual-calendar.tsx`
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
  - Year change triggers Inertia visit to `/day-statuses/{newYear}` using Wayfinder `calendar()` action
  - Use `date-fns` functions: `startOfMonth`, `endOfMonth`, `eachDayOfInterval`, `getDay`, `format`, `isSameDay`, `isToday`
  - Use `useMemo` to pre-compute month data arrays and avoid re-renders
  - Follow Tailwind CSS v4 conventions and existing component patterns

- [x] Task 4: Create the monthly detail view component at `resources/js/components/day-statuses/month-detail.tsx`
  - **Props interface:**
    ```
    {
      year: number
      month: number  // 0-indexed (0 = January)
      dayStatuses: Record<string, ...>  // same as annual calendar
      serviceCounts: Record<string, ...>
      onDayClick: (dateKey: string) => void
      onPrevMonth: () => void
      onNextMonth: () => void
      onBackToYear: () => void
      selectedDate: string | null
    }
    ```
  - **Layout:** Full-width card showing the expanded month
  - Header: Clickable month name + year (navigates back to annual view via `onBackToYear`), ChevronLeft/ChevronRight buttons for prev/next month navigation
  - Grid: 7 columns (Lun, Mar, Mié, Jue, Vie, Sáb, Dom — Spanish weekday abbreviations, week starts on Monday)
  - Each day cell displays:
    - Day number
    - Color indicator (same scheme: black/orange/green)
    - Service count badge (e.g., "5 serv.")
    - For executed days: "Ejecutado" tooltip on hover showing executor name and timestamp
  - Day cells are clickable — triggers `onDayClick(dateKey)` which loads services inline below the calendar
  - Selected day highlighted with `ring-2 ring-blue-500`
  - Today highlighted with `ring-2 ring-primary`
  - Days outside the current month (padding) are displayed dimmed/muted
  - Use `Card` component from shadcn for the container
  - Use `Tooltip` component for executed day hover info

- [x] Task 5: Replace `resources/js/pages/day-statuses/index.tsx` with the calendar implementation
  - **Props** (from controller): `{ dayStatuses, serviceCounts, year, month, selectedDate, dayServices }`
  - **No local state** — the server `month` prop (null = annual, 1-12 = month detail) is the source of truth, driven by the URL path
  - **Breadcrumbs:** `[{ title: 'Calendario', href: calendar(year).url }]`
  - **Page title:** `<Head title="Calendario" />`
  - **Layout:** `AppLayout` wrapper
  - **Rendering logic:**
    - When `month` is null: render `AnnualCalendar` component
    - When `month` is set: render `MonthDetail` + optionally `DayServicesTable` below when `selectedDate` and `dayServices` are present
  - **Month click handler:** Navigate to `/day-statuses/{year}/{month+1}` using Wayfinder `calendarMonth()` action
  - **Day click handler:** Navigate to same month URL with `?selectedDay={day}` query param — loads services inline
  - **Prev/next month handlers:** Navigate to adjacent month URL, handling year boundaries (month 1 → year-1/12, month 12 → year+1/1)
  - **Year change handler:** Navigate to `/day-statuses/{newYear}` using Wayfinder `calendar()` action
  - **Permission:** Page is gated by `VIEW_DAY_SUMMARY` in the controller

- [x] Task 5b: Create `resources/js/components/day-statuses/day-services-table.tsx`
  - Simple read-only table showing services for the selected day
  - Columns: Hora, Ruta (origin → destination), Vehiculo (plate), Conductor, Valor, Estado
  - Each route cell links to the service detail page via Wayfinder `servicesShow()` action
  - Empty state: "No hay servicios para este dia."
  - Uses `components/ui/table` primitives and formatting patterns from `pages/services/columns.tsx`

- [x] Task 6: Update the sidebar navigation link for day-statuses
  - In `resources/js/components/app-sidebar.tsx`:
    - Label: "Calendario"
    - Route: `dayStatusesCalendar(new Date().getFullYear())` (Wayfinder `calendar` action)
    - Icon: `Calendar` from lucide-react

- [x] Task 7: Add Spanish locale configuration for date-fns
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

- [x] Task 8: Create `tests/Feature/Http/Controllers/DayStatusCalendarTest.php` using `php artisan make:test --pest`
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

- [x] Task 9: Update existing DayStatus controller tests (if any) to account for the new index response format
  - Check `tests/Feature/Http/Controllers/DayStatusControllerTest.php` for existing tests
  - Update assertions to match new prop structure (`dayStatuses` as keyed collection, `serviceCounts`, `year`)
  - Ensure no regressions in existing test coverage

## Verification

### UI (Laravel Dusk)

Dusk browser tests in `tests/Browser/`. Use super admin credentials from `env('SUPER_ADMIN_USER')` / `env('SUPER_ADMIN_PASSWORD')`. Run `php artisan migrate:fresh --seed --no-interaction` before tests that need a clean database.

- [x] Navigate to `/day-statuses` and verify redirect to `/day-statuses/{year}` with 12-month annual calendar grid
- [x] Verify today's date is highlighted with a distinct ring/border
- [x] Click a month card and verify navigation to `/day-statuses/{year}/{month}` with monthly detail view
- [x] Verify month detail has prev/next month arrow buttons
- [x] Click next/prev month arrows and verify URL and content update, including year boundary handling
- [x] Click month title and verify navigation back to annual view
- [x] Click a day cell in the monthly view and verify services load inline below the calendar (no page navigation)
- [x] Click the year navigation arrows and verify the calendar navigates to `/day-statuses/{newYear}`
- [x] Verify color-coded day indicators: black (no services), orange (projected), green (executed)

## Dependencies

- `day-status-logic` (pending) — provides auto-created DayStatus records when services are created, and the executed state. Without this, the calendar would only show black (no data) days.

## Notes

- **No external calendar library is used.** The calendar is a custom React component built with date-fns (already installed v4.1.0) and Tailwind CSS. This avoids adding a heavy dependency like FullCalendar for what is essentially a colored grid.
- **Day click loads services inline** below the month calendar. The URL becomes `/day-statuses/{year}/{month}?selectedDay={day}`. The controller reconstructs the full date from the path params + day query param and returns `dayServices` to the frontend.
- **Navigation is path-based.** `/day-statuses` redirects to `/day-statuses/{year}` (annual view). Clicking a month navigates to `/day-statuses/{year}/{month}` (month detail with prev/next arrows). The month title is clickable to return to the annual grid.
- **Service counts are aggregated server-side** with a single GROUP BY query to avoid N+1 issues. The frontend receives pre-computed totals per date.
- **Week starts on Monday** (Colombian/European convention) in the monthly detail grid.
- **The `day-statuses/create`, `edit`, `show` pages** are auto-generated stubs that will NOT be modified in this requirement. Direct CRUD of DayStatus records is not a user workflow — day statuses are managed automatically (via observer) and through the "Execute Day" action.
- **Performance:** For a full year, the maximum number of DayStatus records is 366. Service counts are also at most 366 rows. This data volume is trivial and does not require pagination or lazy loading.
- **Dark mode support:** The color scheme (black/orange/green) works naturally in both light and dark themes. The "black" indicator uses `bg-neutral-800 dark:bg-neutral-700` for visibility. Orange and green use Tailwind's standard palette.
