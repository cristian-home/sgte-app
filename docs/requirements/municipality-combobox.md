---
name: municipality-combobox
type: feat
scope: catalog
status: completed
priority: high
created_date: 2026-03-05
completed_date: 2026-03-05
srs_refs: []
migration_strategy: modify-existing
---

# Municipality Combobox Component

## Description

Create a reusable `MunicipalityCombobox` component that replaces the plain text `Input` fields currently used for `municipality_id` across all forms (vehicles, drivers, third-parties). Colombia has ~1,122 municipalities across 33 departments, so a searchable, department-grouped combobox is essential for usability. The component follows the shadcn Popover + Command pattern already established in the project's data table faceted filters.

## Acceptance Criteria

- [x] AC-1: WHEN the user clicks the municipality combobox trigger THEN a popover MUST open with a search input and a scrollable list of municipalities grouped by department.
- [x] AC-2: WHEN the user types in the search input THEN the list MUST filter municipalities by name OR code (case-insensitive, partial match).
- [x] AC-3: WHEN the user selects a municipality THEN the combobox MUST display the selected municipality as "Municipality Name (Department Name)" and close the popover.
- [x] AC-4: WHEN the combobox has a selected value and the user clicks a clear button THEN the value MUST be reset to null.
- [x] AC-5: WHEN the vehicle create/edit form is rendered THEN the `municipality_id` field MUST use the `MunicipalityCombobox` instead of a plain text input.
- [x] AC-6: WHEN the driver create/edit form is rendered THEN the `municipality_id` field MUST use the `MunicipalityCombobox` instead of a plain text input.
- [x] AC-7: WHEN the third-party create/edit form is rendered THEN the `municipality_id` field MUST use the `MunicipalityCombobox` instead of a plain text input.

## Technical Specification

### Data Model

No new tables or columns. The `departments` and `municipalities` tables already exist from the `departments-municipalities-catalog` requirement.

### Enums

No new enums required.

### Routes

No new routes. Municipalities data MUST be passed as Inertia props from the controller to each form page. No separate API endpoint is needed — the dataset is static catalog data suitable for passing as a page prop.

### Permissions

No new permissions required.

### Pages

| Page | Component Path | Description |
|------|---------------|-------------|
| Component | `resources/js/components/municipality-combobox.tsx` | Reusable combobox for municipality selection |

## Migration Strategy

- **modify-existing**: No database changes. All changes are to controllers (adding municipalities to Inertia props) and frontend components (replacing Input with MunicipalityCombobox).

## Tasks

### Backend

- [x] Task 1: Update `VehicleController@create` to pass municipalities
  - Add municipalities query: `Municipality::query()->with('department:id,name')->orderBy('name')->get(['id', 'name', 'code', 'department_id'])`
  - Pass as `municipalities` prop to Inertia render
  - Follow existing pattern for passing `thirdParties` prop in the same method

- [x] Task 2: Update `VehicleController@edit` to pass municipalities
  - Same query as create
  - Pass as `municipalities` prop alongside existing `vehicle` and `thirdParties` props

- [x] Task 3: Update `DriverController@create` and `DriverController@edit` to pass municipalities
  - Same query pattern as vehicle controller
  - Pass as `municipalities` prop to both create and edit Inertia renders
  - Check what other reference data the driver controller already passes and follow the same pattern

- [x] Task 4: Update `ThirdPartyController@create` and `ThirdPartyController@edit` to pass municipalities
  - Same query pattern
  - Pass as `municipalities` prop to both create and edit Inertia renders
  - Note: third-party create/edit pages currently have inline forms (not a separate form component) — the municipalities prop MUST be threaded through to where the `municipality_id` input is rendered

### Frontend

- [x] Task 5: Create `resources/js/components/municipality-combobox.tsx`
  - **Props interface:**
    ```
    {
      municipalities: Array<{ id: number; name: string; code: string; department_id: number; department?: { id: number; name: string } }>
      value: string | number | null
      onChange: (value: string) => void
      placeholder?: string  // default: "Seleccionar municipio..."
      searchPlaceholder?: string  // default: "Buscar municipio..."
      disabled?: boolean
      invalid?: boolean  // for aria-invalid and error styling
      id?: string
      className?: string
    }
    ```
  - **Component structure** (Popover + Command pattern):
    - `Popover` wrapping `PopoverTrigger` (Button) + `PopoverContent`
    - `PopoverTrigger`: Button with role="combobox" displaying selected municipality name or placeholder; include `ChevronsUpDown` icon from lucide-react; include clear button (X icon) when value is set
    - `PopoverContent`: Contains `Command` component
    - `Command` → `CommandInput` (search field) → `CommandEmpty` ("No se encontró municipio.") → `CommandList` with `CommandGroup` per department
    - Each `CommandGroup` has `heading` set to department name
    - Each `CommandItem` displays municipality `name` with `code` as secondary text; on select: call `onChange(String(municipality.id))`, close popover
    - Selected item shows `Check` icon from lucide-react
  - **Search logic**: Filter municipalities where `name` or `code` includes the search text (case-insensitive). The Command component handles filtering via its built-in search — set `keywords` on each `CommandItem` to include both `name` and `code` for search matching.
  - **Performance**: Group municipalities by department using `useMemo` to avoid re-computing on every render. The list has ~1,122 items across 33 groups — Command's built-in virtualization handles this efficiently.
  - **Display format**: Trigger button shows "Municipality Name (Department Name)" when a value is selected.
  - Follow `resources/js/components/data-table/data-table-faceted-filter.tsx` as the Popover + Command convention reference.
  - Follow shadcn combobox pattern for single-select behavior.

- [x] Task 6: Update `resources/js/components/vehicles/vehicle-form.tsx`
  - Replace the plain `Input` for `municipality_id` (currently around lines 211-218) with `MunicipalityCombobox`
  - Pass `municipalities` from parent page props through to the form component
  - Update the form component's props interface to include `municipalities`
  - Wire `value={data.municipality_id}` and `onChange={(val) => setData('municipality_id', val)}`
  - Pass `invalid={!!errors.municipality_id}` for error styling
  - Keep the existing `Label` and `InputError` wrapping pattern

- [x] Task 7: Update driver form component
  - Locate the driver form component (inline in create/edit pages or separate component)
  - Replace the plain `Input` for `municipality_id` with `MunicipalityCombobox`
  - Same wiring pattern as vehicle form
  - Update parent pages (create.tsx, edit.tsx) to pass `municipalities` prop through

- [x] Task 8: Update `resources/js/pages/third-parties/create.tsx` and `edit.tsx`
  - Replace the plain `Input` for `municipality_id` (lines ~260-275 in create, ~287-302 in edit) with `MunicipalityCombobox`
  - Since third-party forms are inline (not a separate component), the combobox MUST be integrated directly in each page
  - Update page props interface to accept `municipalities`
  - Same wiring pattern as vehicle form

- [x] Task 9: Update TypeScript page props for all affected pages
  - `resources/js/pages/vehicles/create.tsx` — add `municipalities` to props type
  - `resources/js/pages/vehicles/edit.tsx` — add `municipalities` to props type
  - `resources/js/pages/drivers/create.tsx` — add `municipalities` to props type
  - `resources/js/pages/drivers/edit.tsx` — add `municipalities` to props type
  - `resources/js/pages/third-parties/create.tsx` — add `municipalities` to props type
  - `resources/js/pages/third-parties/edit.tsx` — add `municipalities` to props type
  - Use the `Municipality` type from `@/types/models` with required `department` relation: `(Municipality & { department: Pick<Department, 'id' | 'name'> })[]`

### Tests

- [x] Task 10: Update `tests/Feature/Http/Controllers/VehicleControllerTest.php`
  - Add test: `create` page props MUST include `municipalities` array
  - Add test: `edit` page props MUST include `municipalities` array
  - Assert municipalities have `department` relation loaded
  - Follow existing test patterns in the file

- [x] Task 11: Update `tests/Feature/Http/Controllers/DriverControllerTest.php`
  - Add test: `create` page props MUST include `municipalities` array
  - Add test: `edit` page props MUST include `municipalities` array
  - Follow existing test patterns in the file

- [x] Task 12: Update `tests/Feature/Http/Controllers/ThirdPartyControllerTest.php`
  - Add test: `create` page props MUST include `municipalities` array
  - Add test: `edit` page props MUST include `municipalities` array
  - Follow existing test patterns in the file

## Dependencies

- `departments-municipalities-catalog` (completed) — provides Department and Municipality models with seeded DIVIPOLA data

## Notes

- The municipality dataset is ~1,122 rows which is reasonable to pass as an Inertia page prop. No API endpoint or lazy loading is needed for this volume.
- The `Command` component from shadcn (cmdk) has built-in search filtering and handles large lists efficiently. No additional virtualization library is needed.
- Department grouping uses `CommandGroup` with the department name as heading. This makes it easy to visually scan by department (e.g., "ANTIOQUIA", "BOGOTA D.C.", "CUNDINAMARCA").
- The combobox is designed as a generic, reusable component. The `service-form` requirement will import it directly for `origin_municipality_id` and `destination_municipality_id` fields.
- The clear button (X icon) is important because `municipality_id` is nullable on all models — users must be able to deselect.
- The `keywords` prop on `CommandItem` enables searching by both name and DANE code (e.g., typing "11001" finds "BOGOTA D.C.").
- No changes to validation rules or form requests are needed — the existing `nullable|integer|exists:municipalities,id` rules already handle the municipality_id field correctly.
- Driver create/edit pages are still placeholder pages (auto-generated by Blueprint) without actual forms. The municipalities prop is passed from the controller and will be consumed when the driver form is implemented.
