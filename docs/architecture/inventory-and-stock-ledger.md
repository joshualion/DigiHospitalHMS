# Inventory And Stock Ledger Architecture

Status: Phase 5A implemented on 2026-08-24.

Phase 5A introduces a hospital-scoped inventory foundation for medicines and practical non-medicine stock items. It does not implement prescriptions, dispensing, procurement or supplier purchase orders.

## Core Model

- `inventory_locations` stores main stores, pharmacies, ward stores and other configured locations. Locations may be facility-scoped.
- `inventory_units` stores units of measure and controlled pack/base-unit conversion factors.
- `inventory_items` stores generic medicines, brands/products and practical non-medicine items using unique SKU and optional barcode.
- `inventory_batches` stores lot number, manufacture/expiry dates, supplier reference, unit-cost snapshot and batch state.
- `stock_balances` stores derived current quantity by location, item and batch.
- `stock_movements` is the immutable stock ledger.

## Ledger Rules

Balances are never edited directly by application workflows. Quantity changes go through `InventoryLedgerService`, which posts a movement and updates balances in the same transaction.

Movement types currently include:

- `opening_balance`
- `transfer_dispatch`
- `transfer_receipt`
- `adjustment`
- `reversal`

Corrections use reversal movements. Posted movements are not edited or deleted.

## Batch States

Supported states are:

- `quarantine`
- `available`
- `expired`
- `damaged`
- `recalled`
- `exhausted`

Only available, non-expired batches are returned by FEFO suggestions. Expired, quarantined, damaged or recalled batches are excluded from future dispensing candidates.

## Transfers And Adjustments

Transfers are requested, dispatched and received with status history. Dispatch deducts source stock; receipt adds destination stock.

Adjustments require a reason and approval by a different authorized user. Approval posts a ledger movement. Negative stock is blocked.

## Reports

The first report layer includes low-stock, near-expiry, expired-stock and FEFO suggestions. Inventory valuation is intentionally limited to preserving unit-cost snapshots on batches and movements.
