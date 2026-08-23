# Phase 3B Completion Report

Status: implemented on 2026-08-23.

## Delivered

- Configurable hospital-scoped payment methods for cash, transfer, POS/card and other approved methods.
- Cashier shifts with opening float, cash collections, expected cash, counted cash, variance, close and supervisor review.
- Payments linked to patient, facility, cashier and open cash shift where required.
- Partial payment allocation across one or more issued invoices.
- Patient deposits/unallocated credit with later invoice allocation.
- Server-derived invoice paid amount, balance and unpaid/part-paid/paid status.
- Immutable receipt numbers through `NumberSequenceService`.
- Printable receipt and patient payment/account history foundation.
- Payment reversal with reason, authorization and audit history.
- Refund request, approval, rejection and processing workflow with approval separation.
- Refund limits based on received and unrefunded payment amounts.
- Collection summaries by facility and payment method.
- Responsive cashier workbench and accounting review screens.
- Permissions, policies, hospital scoping, IDOR protection and audit/payment event records.

## Tests

Feature tests cover partial and multiple allocations, deposits, receipt numbering, duplicate submission idempotency, cashier shift close/variance, closed-shift cash protection, reversals, refunds, approval separation, authorization, scoping, Inertia page rendering and audit history.

## Deferred

Insurance/HMO, payment gateways, laboratory, pharmacy, inventory and full accounting ledger export remain deferred to later phases.
