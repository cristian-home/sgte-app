# Implementation Plan - SGTE

Technical documentation of the development phases of SGTE (Special Transport Management System).

## Phases

| Phase | Name | Status | Main REQs |
| ----- | ---- | ------ | --------- |
| 1 | [Foundations and Master Data](phase-1-foundations.md) | ✅ Completed | REQ-004, REQ-005, REQ-006, REQ-014 |
| 2 | [Operational Core](phase-2-operations.md) | ✅ Completed | REQ-001, REQ-002, REQ-003, REQ-008, REQ-009 |
| 3 | [Driver and Incidents](phase-3-driver-incidents.md) | ✅ Completed | REQ-012, REQ-013 |
| 4 | [Billing and Audit](phase-4-billing-reports.md) | ⬜ Pending (next) | REQ-011, REQ-009 |
| 5 | [Optional Modules and Deploy](phase-5-optionals-deploy.md) | 🔶 In progress (deploy ready) | REQ-007, REQ-010 |

## Dependencies between phases

```
Phase 1 ✅ ──► Phase 2 ✅ ──► Phase 3 ✅
                        └──► Phase 4 ⬜ (next)
                                  └──► Phase 5 🔶
```

- **Phase 2** requires the migrations and CRUDs from Phase 1
- **Phase 3** requires the service form from Phase 2
- **Phase 4** requires the day states from Phase 2; Phase 3 recommended
- **Phase 5** has no hard blockers; deploy already completed, optional modules pending

## Technology stack

| Layer | Technology |
| ----- | ---------- |
| Backend | Laravel 12 (PHP 8.5) |
| Frontend | React 19 + Inertia.js v2 + shadcn/ui |
| Gantt / Calendar | Custom React components (no external libraries) |
| Database | PostgreSQL 18 |
| Storage | MinIO (S3-compatible) |
| Search | Laravel Scout + Typesense |
| Real-time | Laravel Reverb + Laravel Echo |
| Server | FrankenPHP (Laravel Octane) |
| Hosting | Dokploy + Linux VPS |

## Reference

- [Full SRS](../SRS.md)
- [Data model](../data-model.md)
- [Navigation](../navigation.md)
- [Mockups](../mockups.md)
- [Deployment guide](../deployment.md)
- [Detailed requirements](../requirements/)
- [ADRs](../adr/)
