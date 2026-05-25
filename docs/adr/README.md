# Architecture Decision Records (ADR)

Record of architectural decisions for the SGTE project.

## Index

| ID | Title | Status | Date |
|----|-------|--------|------|
| ADR-001 | [Frontend permission system](ADR-001-frontend-permission-system.md) | Accepted | 2026-02-26 |
| ADR-002 | [SearchesDatabase trait for advanced search](ADR-002-searches-database-trait.md) | Accepted | 2026-03-02 |
| ADR-003 | [Reusable DataTable component](ADR-003-reusable-datatable-component.md) | Accepted | 2026-03-02 |
| ADR-004 | [Hybrid search strategy (Scout+Typesense + SearchesDatabase)](ADR-004-hybrid-search-strategy.md) | Accepted | 2026-04-13 |
| ADR-005 | [Authorization layering (no Eloquent Policies)](ADR-005-authorization-layering.md) | Accepted | 2026-04-13 |
| ADR-006 | [Platform conventions](ADR-006-platform-conventions.md) | Accepted | 2026-04-13 |
| ADR-007 | [Datetime and timezone model](ADR-007-datetime-and-timezone-model.md) | Accepted | 2026-05-08 |

## Format

Each ADR should follow this structure:

```markdown
# ADR-NNN: Decision title

**Status:** Proposed | Accepted | Deprecated | Superseded by ADR-XXX
**Date:** YYYY-MM-DD

## Context

Description of the problem or situation that requires a decision.

## Decision

The decision made and its justification.

## Consequences

Positive and negative impact of the decision.
Accepted trade-offs.
```

## Usage guide

- Number sequentially: ADR-001, ADR-002, etc.
- One ADR per file: `ADR-001-decision-title.md`
- Do not modify accepted ADRs; if the decision changes, create a new one that supersedes it
- Update the index in this README when adding each ADR
