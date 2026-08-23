# Cashier And Payments Guide

Status: Phase 3B.

## Cashier Workbench

Use **Admin > Payments** for daily cashier operations.

1. Open a cashier shift before accepting cash.
2. Select the facility, currency and opening float.
3. Post payments for registered patients.
4. Choose a payment method.
5. Allocate the payment to an issued invoice or leave it unallocated as patient credit.
6. Print the receipt from the receipt page.
7. Close the shift with counted cash at the end of the session.

Cash payments require an open shift. Transfer, POS/card and other configured methods can be posted without a cash shift, subject to permissions and configured references.

## Partial Payments And Deposits

Payments may partially settle one or more issued invoices. Invoice balances and statuses are recalculated by the server. Leaving allocation empty records unallocated patient credit for later invoice allocation.

## Accounting Review

Use **Admin > Payments > Accounting** by opening `/admin/payments/accounting`.

Accounting users can:

- Review closed cashier shifts.
- See collection summaries by payment method and facility.
- Request or process controlled corrections according to their permissions.
- Reverse posted payments with a required reason.
- Approve, reject and process refund requests.

Cashiers cannot approve their own refund request and cannot review their own shift.

## Corrections

Do not edit posted payments or issued receipts. Use:

- Payment reversal for incorrect posted payments.
- Refund request, approval and processing for money returned to a patient.
- Invoice void/replacement from the invoicing workflow for invoice corrections.

## Security Notes

Payment routes are authenticated, role-aware and hospital-scoped. Public routes do not expose payments, invoices or patient account details.
