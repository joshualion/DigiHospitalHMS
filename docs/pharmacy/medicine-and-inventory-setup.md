# Medicine And Inventory Setup Guide

Status: Phase 5A.

Use **Admin > Inventory** for catalogue, stock, transfer, adjustment and inventory report workflows.

## Setup Order

1. Create inventory locations such as main store, pharmacy and ward store.
2. Configure units of measure and pack-to-base conversion factors.
3. Create medicine or non-medicine inventory items with SKU, optional barcode, reorder level and pharmacist/storekeeper notes.
4. Receive opening balances by batch through the stock screen.

All seeded medicines, units and conversions are structural examples only. Pharmacists or storekeepers must validate item names, units, pack conversions and batch handling rules before operational use.

## Stock Handling

Opening stock is posted through an audited receipt movement. Transfers and adjustments also post ledger movements. Do not update database balances manually.

Batch state changes require a reason. Expired, quarantined, damaged and recalled batches are excluded from FEFO suggestions.

## Adjustments

Storekeepers can request adjustments with a reason. A different authorized pharmacist or approver must approve the adjustment before stock changes.

## Reports

Inventory reports currently show:

- Low stock based on reorder levels.
- Near-expiry stock within 90 days.
- Expired batches.
- FEFO batch suggestions.

Prescriptions, dispensing, procurement and purchase orders are not part of Phase 5A.
