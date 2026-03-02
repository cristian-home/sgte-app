# ADR-002: Trait SearchesDatabase para busqueda avanzada en Eloquent

**Estado:** Aceptado
**Fecha:** 2026-03-02

## Contexto

Los modelos que no usan Laravel Scout (por ejemplo, aquellos cuya busqueda es local a la base de datos) necesitan una solucion de busqueda que:
1. Soporte busqueda por subcadena (parcial).
2. Tolere errores tipograficos (fuzzy matching).
3. Permita buscar en columnas del modelo y de relaciones.
4. Permita buscar en multiples columnas concatenadas (nombre completo = nombre + apellido).
5. Funcione en PostgreSQL (produccion) y SQLite (tests).

PostgreSQL tiene la extension `pg_trgm` (v1.6) habilitada con indices GIN trigram en las columnas buscables. La funcion `word_similarity()` permite busqueda difusa encontrando la mejor subcadena coincidente dentro del valor.

## Decision

### 1. Trait reutilizable `SearchesDatabase`

Se creo el trait `App\Models\Concerns\SearchesDatabase` que cualquier modelo Eloquent puede usar implementando el metodo abstracto `searchableColumns()`.

### 2. Formato de columnas buscables

`searchableColumns()` retorna un array que soporta tres formatos:

- **Columna local:** `'column_name'`
- **Columna de relacion (dot notation):** `'relation.column_name'`
- **Columnas compuestas (array):** `['relation.column_a', 'relation.column_b']`

Las columnas compuestas se concatenan con espacios, permitiendo busqueda multi-campo (ej. buscar un nombre completo que abarca dos columnas).

### 3. Estrategia dual en PostgreSQL

En PostgreSQL, cada columna se busca con dos condiciones combinadas via `OR`:

- **`ILIKE '%term%'`** — Coincidencia exacta por subcadena (aprovecha indices GIN trgm).
- **`word_similarity(term, column) >= threshold`** — Coincidencia difusa (tolera errores tipograficos).

Se usa `word_similarity()` en lugar de `similarity()` porque encuentra la mejor subcadena coincidente dentro del valor, lo cual funciona bien tanto para campos cortos (ciudades) como largos (descripciones).

### 4. Umbral configurable por modelo

El metodo `searchSimilarityThreshold()` retorna el umbral de similitud (0.0–1.0). Es sobreescribible por modelo para ajustar la sensibilidad segun el dominio de datos.

### 5. Ordenamiento por relevancia opcional

Se proveen dos scopes publicos:

- **`scopeSearch()`** — Solo filtra resultados. Ideal para vistas donde el ordenamiento lo controla el usuario o Spatie QueryBuilder.
- **`scopeSearchWithRelevance()`** — Filtra y ordena por relevancia usando `GREATEST(word_similarity(...), ...)` DESC. Usa subconsultas correlacionadas para calcular scores de columnas en relaciones BelongsTo.

El controlador elige cual scope usar segun sus necesidades.

### 6. Fallback para SQLite/MySQL

En drivers distintos a PostgreSQL, el trait usa `LIKE '%term%'` sin funciones de trigrama ni ordenamiento por relevancia. Esto permite que los tests (SQLite en memoria) funcionen sin cambios.

## Consecuencias

**Positivas:**
- Reutilizable: cualquier modelo puede adoptar busqueda avanzada implementando un metodo.
- Busqueda por relaciones y campos compuestos sin configuracion adicional en el controlador.
- Tolerancia a errores tipograficos en produccion via pg_trgm.
- Relevancia opcional sin afectar los casos donde no se necesita.
- Compatible con Spatie QueryBuilder via `AllowedFilter::callback`.

**Negativas:**
- La busqueda difusa y el ordenamiento por relevancia solo funcionan en PostgreSQL; en SQLite/MySQL se degrada a subcadena exacta.
- Las subconsultas correlacionadas para scores de relaciones agregan costo en queries con muchas columnas de relacion.
- Solo soporta relaciones BelongsTo para el score de relevancia.

**Archivos clave:**
- `app/Models/Concerns/SearchesDatabase.php` — Trait
- `app/Models/Service.php` — Ejemplo de modelo que usa el trait
- `app/Http/Controllers/ServiceController.php` — Ejemplo de uso con `searchWithRelevance()`
- `tests/Feature/Http/Controllers/ServiceControllerTest.php` — Tests de busqueda
