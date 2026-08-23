# Service Catalogue And Invoicing Architecture

Phase 3A adds the billing catalogue, pricing and draft/issued invoice foundation. It does not add payments, receipts, cashier shifts, insurance/HMO, laboratory, pharmacy or inventory.

## Catalogue

- `billable_service_categories` are hospital scoped.
- `billable_services` are hospital scoped with unique service codes, optional department, optional public marketing service mapping, description, active state, tax configuration and discount eligibility.
- `billable_service_facility` controls facility availability.

Public marketing services are never automatically billed. A billable service may optionally point to a `public_site_items` service record for administrative mapping only.

## Pricing

`service_prices` stores immutable price history. Prices are stored in minor units with a three-letter currency. A price can be hospital-default or facility-specific. Facility-specific prices take precedence over default prices for the same effective date.

The pricing service rejects overlapping active prices for the same service and same facility scope. Tax rate and tax exemption are configured per service or manual line; no country-specific tax assumption is hardcoded.

## Invoices

Invoices are linked to patient and can optionally link visit and encounter. Draft invoices are mutable. Issued invoices receive invoice numbers through `NumberSequenceService`, become immutable and preserve exact service snapshots on each line.

Invoice line totals are always server calculated:

- subtotal = quantity x unit price
- discount is capped at subtotal and only applied when eligible
- tax is calculated from configured basis points unless exempt
- total = subtotal - discount + tax

Manual invoice lines require a reason and authorization. No invoice is marked paid in Phase 3A.

## Corrections

Issued invoices cannot be edited. Corrections require voiding/reversal with a reason and, if needed, creating a replacement draft linked back to the voided invoice.

## Future Integration

Future laboratory, pharmacy and procedure modules should call `InvoiceWorkflowService::addServiceLine()` or a narrow wrapper around it. They must submit service identifiers and quantities only; the billing service resolves price snapshots and totals.
