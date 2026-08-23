# Payments And Reconciliation Architecture

Status: Phase 3B implemented on 2026-08-23.

## Scope

Payments are hospital-scoped financial records linked to a patient, facility, cashier, payment method and, where required, an open cashier shift. Payments can be allocated to one or more issued invoices, or held as unallocated patient credit for later allocation.

This phase does not implement insurance/HMO, payment gateways, laboratory, pharmacy or inventory.

## Money Model

- Amounts are stored in minor units as integers.
- Payment, invoice and refund currency must match before allocation.
- Frontend submitted totals are ignored; invoice paid, balance and payment status are recalculated on the server from posted allocations.
- Posted payments and receipts are immutable. Corrections use reversal and refund workflows.

## Numbering And Idempotency

Receipt numbers are allocated through `NumberSequenceService` using the `receipt_number` sequence. Payment posting accepts an idempotency key scoped to the hospital so duplicate form submissions return the existing payment instead of creating another receipt.

## Cashier Shifts

Cash payment methods require an open cashier shift for the cashier, facility and currency. A shift records:

- Opening float.
- Cash collections from posted cash payments.
- Expected cash.
- Counted cash.
- Variance.
- Open, closed and reviewed states.

Closed shifts cannot accept new cash payments because no open shift can be resolved for the cashier.

## Allocation And Balances

Allocations are transactional. The payment and invoice rows are locked before allocation, invoice balances are recalculated, and allocations are rejected if they exceed either the payment's unallocated amount or the invoice balance.

Invoice payment status is derived as:

- `unpaid` when no posted allocation exists.
- `part_paid` when some balance remains.
- `paid` when balance is zero.

## Reversals And Refunds

Payment reversal reverses posted allocations, recalculates invoice balances and marks the payment `reversed` with actor, timestamp and reason. Financial records are not hard-deleted.

Refunds are request based:

- Cashier or authorized staff requests a refund with amount and reason.
- A separate authorized user approves or rejects; the requester cannot approve their own refund.
- Approved refunds are processed against genuinely received and unrefunded payment amounts.

## Audit

Payment, allocation, shift and refund actions write `payment_events` and application audit events. These include action, actor, before/after payloads, reason and timestamp.

## Extension Point

Future laboratory, pharmacy and procedure modules should add charges by creating draft invoice lines through the existing invoice workflow, then let payment allocation continue to derive balances from issued invoices. They must not directly mutate paid/balance fields.
