# ADR-002: SearchesDatabase trait for advanced Eloquent search

**Status:** Accepted
**Date:** 2026-03-02

## Context

Models that do not use Laravel Scout (for example, those whose search is local to the database) need a search solution that:
1. Supports substring (partial) matching.
2. Tolerates typos (fuzzy matching).
3. Allows searching across model columns and relationship columns.
4. Allows searching across multiple concatenated columns (full name = first name + last name).
5. Works on PostgreSQL (production) and SQLite (tests).

PostgreSQL has the `pg_trgm` extension (v1.6) enabled with GIN trigram indexes on searchable columns. The `word_similarity()` function enables fuzzy search by finding the best matching substring inside the value.

## Decision

### 1. Reusable `SearchesDatabase` trait

The `App\Models\Concerns\SearchesDatabase` trait was created, which any Eloquent model can use by implementing the abstract `searchableColumns()` method.

### 2. Searchable column format

`searchableColumns()` returns an array supporting three formats:

- **Local column:** `'column_name'`
- **Relation column (dot notation):** `'relation.column_name'`
- **Composite columns (array):** `['relation.column_a', 'relation.column_b']`

Composite columns are concatenated with spaces, enabling multi-field search (e.g. searching for a full name that spans two columns).

### 3. Dual strategy on PostgreSQL

On PostgreSQL, each column is searched with two conditions combined via `OR`:

- **`ILIKE '%term%'`** — Exact substring match (leverages GIN trgm indexes).
- **`word_similarity(term, column) >= threshold`** — Fuzzy match (tolerates typos).

`word_similarity()` is used instead of `similarity()` because it finds the best matching substring inside the value, which works well for both short fields (cities) and long ones (descriptions).

### 4. Per-model configurable threshold

The `searchSimilarityThreshold()` method returns the similarity threshold (0.0–1.0). It is overridable per model to tune sensitivity to the data domain.

### 5. Optional relevance ordering

Two public scopes are provided:

- **`scopeSearch()`** — Only filters results. Ideal for views where ordering is controlled by the user or Spatie QueryBuilder.
- **`scopeSearchWithRelevance()`** — Filters and orders by relevance using `GREATEST(word_similarity(...), ...)` DESC. It uses correlated subqueries to compute scores for columns on BelongsTo relations.

The controller chooses which scope to use based on its needs.

### 6. SQLite/MySQL fallback

On drivers other than PostgreSQL, the trait falls back to `LIKE '%term%'` without trigram functions or relevance ordering. This allows tests (in-memory SQLite) to run unchanged.

## Consequences

**Positive:**
- Reusable: any model can adopt advanced search by implementing a single method.
- Search across relationships and composite fields without extra configuration in the controller.
- Typo tolerance in production via pg_trgm.
- Optional relevance ordering without affecting cases where it isn't needed.
- Compatible with Spatie QueryBuilder via `AllowedFilter::callback`.

**Negative:**
- Fuzzy search and relevance ordering only work on PostgreSQL; on SQLite/MySQL it degrades to exact substring matching.
- Correlated subqueries for relation scores add cost when queries include many relation columns.
- Only BelongsTo relations are supported for the relevance score.

**Key files:**
- `app/Models/Concerns/SearchesDatabase.php` — Trait
- `app/Models/Service.php` — Example model using the trait
- `app/Http/Controllers/ServiceController.php` — Example usage with `searchWithRelevance()`
- `tests/Feature/Http/Controllers/ServiceControllerTest.php` — Search tests
