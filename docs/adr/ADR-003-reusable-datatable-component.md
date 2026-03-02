# ADR-003: Componente DataTable reutilizable con TanStack React Table

**Estado:** Aceptado
**Fecha:** 2026-03-02

## Contexto

Multiples modulos del sistema (servicios, vehiculos, conductores, contratos, etc.) requieren listados tabulares con funcionalidades comunes: busqueda, paginacion server-side, filtros facetados, ordenamiento por columnas y visibilidad de columnas. Implementar esto individualmente en cada pagina generaria duplicacion significativa.

Se necesita un sistema de componentes que:
1. Soporte paginacion y busqueda server-side via Inertia.
2. Permita filtros facetados configurables por vista.
3. Soporte ordenamiento por columnas sincronizado con el backend.
4. Sea extensible para distintos modelos sin modificar el componente base.

## Decision

### 1. Arquitectura de componentes

Se creo un sistema de componentes bajo `resources/js/components/data-table/` basado en TanStack React Table, con separacion de responsabilidades:

| Componente | Responsabilidad |
|---|---|
| `data-table.tsx` | Contenedor principal. Acepta modo server-side (con `PaginatedData`) o client-side (con `data[]` + `columns[]`). |
| `data-table-toolbar.tsx` | Barra superior: campo de busqueda, filtros facetados, visibilidad de columnas y acciones. |
| `data-table-pagination.tsx` | Paginacion inferior: selector de registros por pagina, navegacion de paginas, conteo de resultados. |
| `data-table-column-header.tsx` | Encabezado de columna con indicadores de ordenamiento (ascendente/descendente). |
| `data-table-faceted-filter.tsx` | Filtro popover multi-seleccion con busqueda integrada. |
| `data-table-view-options.tsx` | Dropdown para mostrar/ocultar columnas dinamicamente. |
| `data-table-row-actions.tsx` | Menu desplegable de acciones por fila (editar, eliminar). |

### 2. Hook `useServerTable`

Se creo el hook `resources/js/hooks/use-server-table.ts` que encapsula toda la logica de estado server-side:

- Sincroniza busqueda, filtros, ordenamiento y paginacion con parametros URL via `router.visit()` de Inertia.
- Debounce configurable en la busqueda (300ms por defecto).
- Maneja el estado de carga (`loading`) durante navegaciones Inertia.
- Retorna la instancia de TanStack Table junto con handlers listos para pasar al componente `DataTable`.

### 3. Patron de uso por modulo

Cada modulo define su tabla en dos archivos dentro de su directorio de pagina:

- **`columns.tsx`** — Define las `ColumnDef[]` de TanStack con `meta.label` para las etiquetas, `DataTableColumnHeader` para ordenamiento, y `DataTableRowActions` para acciones.
- **`index.tsx`** — Pagina Inertia que recibe `PaginatedData` del backend, inicializa `useServerTable()`, define `FilterDefinition[]` y renderiza `<DataTable>`.

### 4. Integracion con el backend

El componente se integra con Spatie QueryBuilder en el controlador:

- `filter[search]` — Parametro de busqueda (procesado por el trait `SearchesDatabase` o Scout).
- `filter[campo]` — Filtros exactos facetados.
- `sort` / `-sort` — Ordenamiento ascendente/descendente.
- `per_page` — Registros por pagina.
- `page` — Numero de pagina.

El formato de `PaginatedData<T>` mapea directamente la respuesta de `->paginate()->withQueryString()` de Laravel.

### 5. Tipos compartidos

Los tipos se definen en:
- `resources/js/types/pagination.ts` — `PaginatedData<T>`, `PaginationLink`
- `resources/js/types/data-table.ts` — `FilterOption`, `FilterDefinition`

## Consecuencias

**Positivas:**
- Agregar un listado nuevo solo requiere definir columnas y filtros; el comportamiento de busqueda, paginacion y ordenamiento viene integrado.
- Consistencia visual y funcional entre todos los modulos.
- Server-side por defecto: la paginacion y busqueda no cargan todos los registros en el cliente.
- Sincronizacion URL bidireccional: los filtros y pagina son compartibles via URL.

**Negativas:**
- Acoplamiento a TanStack React Table como dependencia de UI.
- Agregar funcionalidad a una tabla especifica puede requerir entender la API de TanStack.
- El hook `useServerTable` asume la estructura de paginacion de Laravel; otro formato requeriria adaptacion.

**Archivos clave:**
- `resources/js/components/data-table/` — Componentes del sistema de tabla
- `resources/js/components/ui/table.tsx` — Primitivas HTML base
- `resources/js/hooks/use-server-table.ts` — Hook de estado server-side
- `resources/js/types/pagination.ts` — Tipos de paginacion
- `resources/js/types/data-table.ts` — Tipos de filtros
- `resources/js/pages/services/index.tsx` — Ejemplo de implementacion
- `resources/js/pages/services/columns.tsx` — Ejemplo de definicion de columnas
