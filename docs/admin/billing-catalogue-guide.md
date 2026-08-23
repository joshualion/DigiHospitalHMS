# Billing Catalogue Guide

Use `Billing` in the admin sidebar for invoice work and `/admin/billing/catalogue` for the service catalogue.

## Catalogue

Create service categories first, then billable services. Each service requires a unique hospital-scoped code and can optionally be linked to a department, facility list and public marketing service. Public mappings are informational and do not bill visitors or public pages.

## Pricing

Add prices in minor units, for example `10000` for 100.00 in a currency with two decimal places. A blank facility means default price. A facility price overrides the default price for that facility when the effective date matches. Overlapping active prices for the same service and facility scope are rejected.

## Invoices

Create a draft invoice for a patient and optionally link visit and encounter. Add service lines from the catalogue or authorized manual lines with a reason. The server calculates all totals; do not rely on browser totals.

Issue the invoice only after review. Issued invoices cannot be changed. Use void with a reason and create a replacement draft when a correction is required.

Payments, receipts, cashier shifts, insurance/HMO and payment status are not available in Phase 3A.
