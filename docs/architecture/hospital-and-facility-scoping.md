# Hospital And Facility Scoping

Date: 2026-08-14

## Deployment Model

Phase 1A supports one isolated installation and database per hospital deployment, with one primary hospital record and multiple facilities or branches. The schema uses explicit `hospital_id` and `facility_id` relationships so a future shared-database SaaS edition is not blocked.

No tenancy package or hidden global tenant scope was introduced.

## Hospital

The `hospitals` table stores legal identity, display name, registration reference, contacts, address, timezone, logo reference, status, primary contact, default currency, and timestamps. Nigeria-safe defaults are provided by the seeder and validation paths where appropriate:

- Country: Nigeria
- Timezone: Africa/Lagos
- Currency: NGN

These are configurable values, not hardcoded deployment limits.

## Facilities

Facilities belong to a hospital. Facility codes are unique within the hospital. One facility can be marked primary, and setting a new primary facility clears the previous primary flag inside the same hospital.

Facilities are deactivated rather than deleted. Deactivation does not remove memberships or historical references.

## Departments

Departments are hospital-wide by default, with an optional `facility_id` for site-specific departments. This avoids duplicating common departments across branches while still allowing a facility-specific unit when needed.

No clinical department workflows were implemented.

## Staff Memberships

A staff profile belongs to one hospital. Facility memberships allow a staff member to belong to one or more facilities inside that hospital, with one default facility. A membership alone does not grant access; active user status, active staff status, roles, permissions, policy checks, and hospital/facility scope all apply.
