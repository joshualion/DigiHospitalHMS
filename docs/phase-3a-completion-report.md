# Phase 3A Completion Report

Status: implemented on 2026-08-23.

## Delivered

- Hospital-scoped billable service categories and services.
- Unique service codes, descriptions, department association, facility availability, active state and optional public-service mapping.
- Default and facility-specific price history in minor units with currency.
- Effective-date price precedence and overlap protection.
- Configurable tax exemption, tax basis points and discount eligibility without country-specific assumptions.
- Draft invoices linked to patient, visit and encounter.
- Server-calculated service and manual invoice lines with service snapshots, quantity, discount, tax and totals.
- Invoice number allocation through `NumberSequenceService` on issue.
- Draft, issued, cancelled, voided and replacement-draft workflows.
- Issued invoice immutability and controlled void/replacement correction path.
- Patient invoice history foundation, Inertia admin screens, permissions, policies, scoping, IDOR protection and audit events.

## Tests

Feature tests cover pricing precedence, effective dates, overlap rejection, server-side calculations, numbering, authorization, draft changes, issue, immutability, void/replacement, scoping, page rendering and audit history.

## Deferred

Payments, receipts, cashier shifts, insurance/HMO, laboratory, pharmacy and inventory were not implemented.
