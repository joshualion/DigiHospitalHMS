# Architecture Recommendation

Date: 2026-08-14

## Current Architecture

The repository is currently a Laravel 12 monolith using Blade, Livewire 3, Volt, Tailwind CSS, Alpine.js, Vite, Lucide Blade icons, and Spatie Laravel Permission.

There is no API layer, no `routes/api.php`, no Inertia.js, no Vue, no React, no SPA router, and no separate frontend application. Existing screens are Blade views and Volt components.

## Recommendation

Recommended approach for the next phase: **Laravel with Livewire 3, Volt where useful, Alpine.js, and a modular monolith structure**.

This is closest to option 2: Laravel with Livewire and Alpine.js.

Do not introduce a separate Vue SPA or full API-first split for the initial recovery/MVP. Do not introduce Inertia.js yet unless, after stabilising the codebase, the team strongly prefers Vue and accepts the migration cost.

## Rationale

Evidence from the repository:

- Existing auth is already Livewire/Volt.
- Existing views are Blade.
- Existing visual language is Tailwind-based.
- There is no Vue/Inertia code to preserve.
- The codebase is extremely small; the bottleneck is missing domain/backend architecture, not frontend interactivity.
- A small team can build SPA-like workflows with Livewire, Alpine, and well-structured Blade components while keeping a single Laravel deployment.

## Option Comparison

| Option | Existing code preserved | Speed | Complexity | Security | Maintainability | Hosting cost | Offline/unreliable network | Testing | Commercial viability |
|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| Blade progressively enhanced | High | High | Low | Good | Good if disciplined | Low | Good on LAN | Good | Good for simple workflows, weaker for complex clinical screens |
| Livewire + Alpine | High | High | Moderate | Good with Laravel sessions/policies | Good if modularized | Low | Good on LAN, fewer API auth concerns | Good | Best initial fit |
| Inertia + Vue 3 | Medium-low | Medium | Moderate-high | Good but more frontend state | Good with Vue discipline | Low-medium | Good, but heavier frontend | Good | Viable later if team wants Vue UX |
| Laravel API + separate Vue SPA | Low | Low initially | High | Higher auth/CORS/token complexity | Higher operational burden | Higher | More moving parts | More test surfaces | Not justified by current repo |
| Microservices | Very low | Low | Very high | Harder | Harder | Higher | Harder | Harder | Not justified |

## Proposed Modular Monolith

Keep one Laravel application and organize around bounded modules:

- Identity and Access
- Tenancy and Facilities
- Patients
- Appointments and Queue
- Encounters
- Admissions and Nursing
- Laboratory
- Radiology
- Pharmacy
- Inventory and Procurement
- Blood Bank
- Theatre and Procedures
- Billing and Payments
- Insurance and Corporate Accounts
- Reporting
- Notifications
- Audit and Compliance
- Platform Administration

Suggested first-party structure:

```text
app/
  Domain/
    Patients/
      Actions/
      Data/
      Events/
      Models/
      Policies/
      Queries/
      Rules/
    Billing/
    Laboratory/
    ...
  Http/
    Controllers/
    Requests/
    Resources/
  Livewire/
    Patients/
    Billing/
    ...
```

For a small team, do not over-engineer with repositories for every model. Use action/service classes where workflows span multiple models or require transactions.

## Engineering Conventions

- Use form request classes or Livewire validation objects for all writes.
- Use policies for model-level access and Spatie permissions for role capability checks.
- Enforce hospital/facility scope in both queries and policies.
- Use database transactions for billing, inventory, dispensing, lab approval, blood-bank chain of custody, admission transfer, and discharge workflows.
- Use domain events for auditable completed actions, notifications, and downstream reporting.
- Use queued jobs for reminders, reports, imports/exports, backups, SMS/email, and integrations.
- Use state machines or controlled enums/statuses for workflows with approvals.
- Avoid JSON columns for core relational clinical data; reserve JSON for flexible templates, external payload snapshots, and non-query-critical metadata.
- Add indexes around tenant/facility, patient, encounter, status, date, and foreign-key lookup paths.
- Use soft deletes only where appropriate, but do not silently delete clinical/financial records. Prefer void/cancel/reverse workflows with reason and actor.

## Multi-Hospital Deployment Model

Recommended initial commercial model: **hybrid strategy, starting with isolated deployments per hospital, then adding hosted SaaS multi-tenancy once the domain model, authorization, audit, and operational workflows are mature**.

### Dedicated Installation and Database Per Hospital

Benefits:

- Strongest practical data isolation for early releases.
- Simpler security story for clinical data.
- Easier per-hospital customization and backup/restore.
- Lower risk while schemas and workflows are evolving.
- Simpler licensing for initial pilots.

Costs:

- More deployments to maintain.
- Upgrade orchestration is harder as customer count grows.
- Cross-hospital analytics and central administration require extra tooling.

### Shared Multi-Tenant SaaS

Benefits:

- Lower per-customer infrastructure cost at scale.
- Centralized upgrades and monitoring.
- Easier subscription management and platform-wide analytics.

Costs and risks:

- Requires strict tenant isolation everywhere.
- Requires tenant-aware roles, permissions, jobs, files, reports, backups, exports, logs, and support access.
- Tenant-data leakage becomes a top-tier commercial and safety risk.
- More complex migrations and customization strategy.

### Hybrid Model

Benefits:

- Supports early dedicated installs and later SaaS growth.
- Allows larger hospitals to buy dedicated deployments while smaller clinics use hosted SaaS.
- Gives time to mature platform administration and tenant isolation.

Costs:

- Requires disciplined configuration and deployment automation.
- Feature flags and licensing must work in both modes.

## Recommended Commercial Path

1. Recover the current Laravel monolith.
2. Build hospital/facility foundations in a way that can support both single-hospital and tenant-aware deployments.
3. Release early pilots as isolated hospital deployments.
4. Add SaaS tenancy only after core workflows, audit logs, backups, and permissions pass isolation tests.

## Security Architecture Foundations

Required before pilot:

- `APP_DEBUG=false` outside development.
- Environment-specific config and deployment checklist.
- Least-privilege RBAC.
- Hospital/facility scoping in database schema and application queries.
- Policies for every protected model.
- Audit log for clinical, financial, access-control, export, login, and administrative events.
- Secure document storage with signed/temporary access.
- Backup and restore procedures.
- Login throttling, session controls, optional MFA plan.
- Authorization tests and IDOR tests.
- Nigeria Data Protection Act considerations must be handled with legal review; this document is technical planning, not legal advice.

## UI Shell Recommendation

Keep the existing Tailwind visual direction but rebuild the operational app shell around role-aware navigation:

- Platform administrator
- Hospital administrator
- Receptionist
- Doctor
- Nurse
- Pharmacist
- Laboratory scientist
- Radiology staff
- Blood-bank staff
- Cashier
- Accountant
- Storekeeper
- HMO/claims officer

Use Livewire for SPA-like interaction patterns:

- Searchable tables
- Modals/drawers
- Wizard workflows
- Inline validation
- Optimistic loading states where safe
- Polling for queues and lab/pharmacy worklists

Do not redesign the UI during recovery. First stabilize the backend, app shell, auth, roles, and data model.
