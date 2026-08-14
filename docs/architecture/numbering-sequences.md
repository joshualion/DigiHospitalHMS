# Numbering Sequences

Date: 2026-08-14

## Purpose

Phase 1A creates a numbering foundation for future domain records without creating those records.

Prepared sequence keys:

- `patient_number`
- `visit_number`
- `invoice_number`
- `receipt_number`
- `lab_request_number`
- `prescription_number`
- `admission_number`

## Behaviour

Sequences are hospital-scoped and may optionally be facility-scoped. Each sequence stores a prefix, optional date format, padding length, next value, issued count, active status, and preview format.

`app/Services/NumberSequenceService.php` allocates numbers inside a database transaction with `lockForUpdate()` to prevent duplicate allocation under concurrent access.

Once a sequence has issued numbers, unsafe configuration changes are blocked for prefix, date format, padding length, and next value. Any controlled change must be audited.

No patient, visit, invoice, receipt, lab, prescription, or admission records were created in Phase 1A.

