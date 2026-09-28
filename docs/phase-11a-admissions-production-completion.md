# Phase 11A: Admissions Production Completion

Phase 11 begins the production-completion work that follows the original Phase 10 roadmap.

## Objective

Convert admissions/bed management from a verified development workflow into a clean production administration module.

## Scope

- Derive occupied-bed census from real active admissions.
- Detect stale occupied beds whose admission no longer exists.
- Provide an audited bed-state reconciliation action.
- Add complete update/delete CRUD for bed classes, wards, rooms and beds.
- Protect destructive deletion when a record is genuinely in active use.
- Provide a hospital-admin-only pre-production admissions demo reset.
- Replace unexplained bed-board actions with plain operational language and help text.
- Remove Phase 6A development artefacts from normal production workflow.

## Bed-state meanings

- Available: ready for a patient.
- Reserved: temporarily held for an approved admission.
- Occupied: backed by an active admitted/transferred patient.
- Cleaning: patient has left; bed awaits turnaround before becoming available.
- Maintenance: unavailable because repairs/technical work are required.
- Blocked: deliberately unavailable for another operational reason.
- Inactive: bed is no longer in service.

## Safety boundary

The demo reset is intended only before a hospital starts recording real admissions. Normal live admissions remain historical records and are not hard-deleted through routine CRUD.
