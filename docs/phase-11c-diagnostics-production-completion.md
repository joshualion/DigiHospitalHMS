# Phase 11C: Diagnostics Production Completion

## Objective

Complete Laboratory and Radiology catalogue administration for production use while preserving historical diagnostic records.

## Scope

- Laboratory specimen type CRUD.
- Laboratory unit CRUD.
- Laboratory test CRUD.
- Laboratory panel/profile CRUD.
- Radiology modality CRUD.
- Radiology study CRUD.
- Active/inactive lifecycle for diagnostic catalogue records.
- Dependency-aware deletion protection once a record is referenced by diagnostic history.
- Catalogue UIs support create, edit and delete.
- Historical requests, results and reports remain protected and append-only.

## Safety boundary

Clinical diagnostic history is never hard-deleted through routine catalogue administration. Obsolete catalogue items should be inactivated when historical dependencies exist.
