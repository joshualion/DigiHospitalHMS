# Identity And Access

Date: 2026-08-14

## Phase 1A Position

`users` remains the authentication identity. Employment and access context lives outside authentication fields:

- `users`: login identity, `firstname`, `lastname`, email, password, status, and legacy `access_level`.
- `staff_profiles`: hospital employment/professional profile.
- `facility_memberships`: facility assignment and default facility.
- Spatie Permission roles and permissions: authorization source of truth.

The legacy `access_level` column is retained for compatibility and factory defaults, but it is no longer the primary authorization mechanism. It should be removed or fully retired in a later migration only after all remaining references are audited.

## Roles

Phase 1A seeds these roles:

- `superadmin`
- `admin`
- `hospital-admin`
- `receptionist`
- `doctor`
- `nurse`
- `pharmacist`
- `laboratory-scientist`
- `radiology-staff`
- `cashier`
- `accountant`
- `storekeeper`
- `blood-bank-staff`
- `hmo-claims-officer`
- `patient`

Only implemented foundation permissions are operational in Phase 1A. Clinical, billing, pharmacy, laboratory, inventory, blood-bank, admissions, and patient-portal permissions remain future work.

## Foundation Permissions

- `hospital.view`
- `hospital.update`
- `facilities.view`
- `facilities.create`
- `facilities.update`
- `facilities.activate`
- `departments.view`
- `departments.manage`
- `staff.view`
- `staff.invite`
- `staff.update`
- `staff.suspend`
- `staff.assign-facilities`
- `roles.view`
- `roles.assign`
- `permissions.manage`
- `audit.view`
- `audit.export`
- `settings.manage`
- `numbering.manage`

`superadmin` receives every foundation permission. `admin` and `hospital-admin` receive foundation administration permissions except sensitive permission-management and audit-export permissions. Operational roles receive read-only foundation context required for future workspaces.

## Guardrails

- Admin routes require authentication, an active account, and an administration role.
- Model policies enforce server-side permission checks and hospital scoping.
- Ordinary authenticated users cannot access administration pages.
- Non-superadministrators cannot assign the `superadmin` role.
- The final active superadministrator cannot be suspended.
- Suspended accounts are logged out and blocked by middleware.

