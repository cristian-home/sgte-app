# ADR-004: Hybrid search strategy (Scout+Typesense for catalogs, SearchesDatabase for Service)

**Status:** Accepted
**Date:** 2026-04-13
**Supersedes:** nothing. Complements ADR-002.

## Context

The project currently uses **two different search strategies** side by side:

- **Laravel Scout + Typesense**: enabled on every catalog / reference model. `Vehicle`, `Driver`, `ThirdParty`, `Contract`, `Invoice`, `DocumentType`, `Eps`, `PensionFund`, `SeveranceFund`, `IncidentType`, and `DayStatus` all use `Laravel\Scout\Searchable` with a custom `toSearchableArray()`. The deployment already provisions a Typesense container and `SCOUT_DRIVER=typesense`.
- **`SearchesDatabase` trait** (ADR-002): applied to the `Service` model. Queries hit PostgreSQL directly using `pg_trgm` and `word_similarity`.

This split is not accidental, but it has never been documented. A new developer reading ADR-002 reasonably asks: "Why is the highest-volume searchable model (`Service`) the one that is *not* indexed in Typesense?"

## Decision

We keep the hybrid strategy and document the rationale here.

### Catalog / reference models → Scout + Typesense

Catalog tables are **small**, **stable**, and benefit from **fuzzy cross-field ranking**. A user searching for "Juan Per" against `drivers` expects results across `first_name`, `first_lastname`, `identification_number`, `email`, and optionally the `eps.name` or `municipality.name` relationships. Typesense's typo tolerance + multi-field BM25 scoring is a better fit than SQL trigram joins, and the index is cheap to keep warm (the dataset fits in memory).

These models are also the ones users search from autocompletes — the latency budget is small, and a dedicated search engine makes the UX snappy without burdening Postgres with concurrent trigram queries.

### `Service` → `SearchesDatabase` (DB-local)

The `Service` model is the highest-volume transactional entity. Almost every `Service` query is **bounded by date range + relational filters** (driver, third-party, contract, vehicle, municipality) before any text search kicks in. Typesense doesn't model those relational filters well; reproducing them would require flattening FK-linked data into the index on every write, which in turn needs a queue worker for every `Service` update plus every related model update (cascade reindexing).

For this model the right shape is:

1. Postgres-side date/relational filter (a tight index scan).
2. Apply `word_similarity()` substring match on `origin_address` / `destination_address` / concatenated driver name / third-party name **inside** the filtered window.
3. Optionally rank by relevance with `word_similarity()` ordering.

This is exactly what the `SearchesDatabase` trait does, and it keeps the service index free from the complexity of flattening.

### Why not switch everything to Typesense?

- **Relational-scoped queries are awkward**: bounded-by-date-and-FK filters are Postgres's strength, not Typesense's. The trade-off would be continuous reindexing for every service-adjacent mutation.
- **Operational surface**: Scout is already a cross-cutting dependency for the catalogs; adding `Service` would double the indexing throughput on the queue.
- **Transactional consistency**: an operator creating a service and immediately filtering the Gantt expects the result to be visible. Postgres gives read-your-own-writes for free; Typesense gives it only after the reindex job drains.

### Why not switch everything to `SearchesDatabase`?

- **Fuzzy multi-field autocompletes** over catalogs would compete with transactional queries on the same Postgres instance.
- **Ranking quality**: BM25 on Typesense produces better "first-match" results than `word_similarity` for catalog lookup UX.

## Consequences

**Positive:**

- Each model uses the tool best suited to its access pattern.
- No reindexing cost for `Service` mutations.
- Catalog autocompletes stay snappy under load.

**Negative:**

- Two search stacks to keep operational: Scout + Typesense for most models, Postgres trigram indexes for `Service`.
- Newcomers must learn which strategy applies to which model. This ADR is the place they find out.
- If `Service` search ever needs cross-model BM25-style ranking, it would require rebuilding the strategy; the trait doesn't scale to that.

**Key files:**

- Models using Scout: `app/Models/Vehicle.php`, `Driver.php`, `ThirdParty.php`, `Contract.php`, `Invoice.php`, `DocumentType.php`, `Eps.php`, `PensionFund.php`, `SeveranceFund.php`, `IncidentType.php`, `DayStatus.php`
- Model using the trait: `app/Models/Service.php`
- Trait: `app/Models/Concerns/SearchesDatabase.php` (see ADR-002)
- Scout config: `config/scout.php`, env var `SCOUT_DRIVER=typesense`
- Compose services: `compose.yaml` (Typesense), `compose.staging.yaml` (Typesense)

## When to revisit

Reopen this decision if:

- `Service` text search starts needing cross-field BM25 ranking.
- The operator workflow requires service search to be untethered from date/FK filters.
- A reindexing storm from catalog updates becomes a visible latency problem in production.
