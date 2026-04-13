---
name: email-notifications
type: feat
scope: notifications
status: completed
priority: high
created_date: 2026-03-22
completed_date: 2026-03-22
srs_refs: ["REQ-013"]
migration_strategy: new
---

# Email Notifications

## Description

Implement 5 types of email notifications using Laravel Notifications. Includes a schedulable artisan command to check document and license expirations daily.

## Acceptance Criteria

- [x] WHEN a service is created with a linked driver THEN the driver receives an assignment email
- [x] WHEN a vehicle document expires in 30/15/5 days THEN administrators receive an alert email
- [x] WHEN a driver license expires in 30/15/5 days THEN administrators receive an alert email
- [x] WHEN an incident affecting billing is registered THEN admin + accounting receive an email
- [x] WHEN a day is executed THEN users with the accounting role receive an email
- [x] WHEN `app:check-expirations` is run THEN all documents and licenses are checked

## Implementation Summary

- 5 Notification classes in `app/Notifications/`
- `CheckExpirations` artisan command scheduled daily at 07:00
- Notifications dispatched inline in controllers (ServiceController, ServiceIncidentController, DayStatusController)
- All notifications implement `ShouldQueue` for async processing
- 10 tests covering rendering and dispatch
