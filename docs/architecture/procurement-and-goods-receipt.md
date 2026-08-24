# Procurement And Goods Receipt Architecture

Phase 5C adds hospital-scoped suppliers, purchase requisitions, purchase orders and goods receipts on top of the Phase 5A immutable stock ledger.

## Model

- Suppliers are scoped by hospital and may be linked to supplied inventory items.
- Purchase requisitions are drafted from an inventory location and carry server-calculated minor-unit totals.
- Submitted requisitions require a separate approver. Approval is checked against `procurement_approval_limits`.
- Approved requisitions convert into numbered purchase orders through `NumberSequenceService`.
- Purchase orders snapshot supplier and item details; approved pricing and quantities are not edited after conversion.
- Goods receipt notes are linked to purchase orders, suppliers, facilities and receiving locations.
- Accepted quantities create inventory batches and post `goods_receipt` stock movements through `InventoryLedgerService`.
- Rejected quantities remain on the receipt line with a mandatory reason and do not affect stock.

## Corrections

Supplier returns and receipt reversals are append-only. They create new stock ledger movements and procurement events; they never delete receipt or PO history.

## Deferred

Supplier payments, accounts payable, tenders, vendor scoring and accounting integration are intentionally outside Phase 5C.
