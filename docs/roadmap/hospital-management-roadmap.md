# Hospital Management Roadmap

Date: 2026-08-14

## Roadmap Principles

- Stabilize the repository before adding hospital modules.
- Build a modular Laravel monolith.
- Deliver one verified workflow at a time.
- Require tests and authorization for every protected workflow.
- Treat clinical, blood-bank, pharmacy, billing, and audit workflows as safety-critical.
- Start with isolated hospital deployments before shared SaaS multi-tenancy.

## Phase 0: Repository Recovery and Development Environment Stabilisation

Objective: Make the existing Laravel project install, boot, route-list, test, and build reliably.

Modules/workflows:

- Environment setup.
- Auth scaffold repair.
- Test harness repair.
- Static analysis/format baseline.
- Broken view/route inventory cleanup.

Dependencies:

- PHP extensions, especially `intl`.
- MySQL or SQLite testing strategy.

Database work:

- No new hospital schema.
- Decide whether `users` keeps `firstname`/`lastname` or reintroduces `name` as computed/display field.
- Repair factories/tests without destructive migrations.

Backend work:

- Fix missing logout controller route or wire existing Livewire logout action correctly.
- Add model relationships for `Page`, `Section`, and `Block` if CMS is retained.
- Fix invalid CMS pagination.
- Align admin dashboard user display fields.

Interface work:

- Replace missing route targets with real placeholder pages or remove routes until implemented.
- Keep visual design intact.

Authorization work:

- Confirm Spatie role middleware.
- Register permission middleware aliases if needed.

Tests:

- Make scaffold tests pass or remove misleading scaffold tests only after replacing them with accurate tests.
- Add smoke tests for route list, auth login/logout, admin access.

Documentation:

- Local setup guide.
- Environment checklist.
- Known limitations.

Acceptance criteria:

- `composer validate --strict` passes.
- `php artisan route:list` passes.
- `php artisan test` passes for retained tests.
- `npm run build` passes.
- `vendor/bin/pint --test` passes or documented baseline exists.

Risks:

- Existing MySQL data may contain test artifacts from current failed tests.
- No Git repository means change tracking is weak; initialize source control before implementation.

Deferred:

- No hospital modules yet.

Implementation sessions:

1. Initialize source control or confirm external VCS location.
2. PHP extension and environment checklist.
3. Auth route/controller repair.
4. User schema/factory/test alignment.
5. CMS relationship/pagination repair or temporary removal from navigation.
6. Missing view route cleanup.
7. Baseline tests/build/style verification.

## Phase 1: Architecture, Authentication, Roles, Facilities, Settings, and Audit Foundations

Objective: Establish the commercial foundation for a hospital deployment.

Status: Phase 1A implemented on 2026-08-14 for hospital administration foundations. Public website reconstruction and frontend content management are split into Phase 1B.

Modules/workflows:

- Identity and access.
- Hospital profile.
- Facilities/branches.
- Departments.
- Staff accounts.
- Roles/permissions.
- Audit events.
- Settings and numbering sequences.

Dependencies:

- Phase 0 complete.

Database work:

- Hospitals/facilities/departments.
- Staff profiles and facility memberships.
- Hospital settings and numbering sequences.
- Audit event tables.
- Tighten users mass assignment.

Backend work:

- Service/action classes for staff invitation, role assignment, settings updates.
- Policies and permission checks.
- Audit logging on protected writes.

Interface work:

- Application shell with role-aware navigation.
- Admin settings screens.
- Staff and roles screens.

Authorization work:

- Least-privilege permissions.
- Policies for hospital, facility, user, staff, settings.

Tests:

- Auth, roles, permissions, facility scoping, audit events.

Documentation:

- Admin setup guide.
- Permission matrix.

Acceptance criteria:

- A hospital admin can configure one hospital, facilities, departments, staff, roles, and settings.
- Every protected write is authorized and audited.

Risks:

- Overly broad roles can create privilege escalation.

Deferred:

- Full SaaS tenancy, patient portal, MFA enforcement.

Implementation sessions:

1. Module folder conventions and base app shell.
2. Hospital/facility/department schema.
3. Staff profile and facility membership.
4. Permission matrix and policies.
5. Audit log foundation.
6. Settings and numbering sequences.
7. Admin UI and tests.

## Phase 1B: Public Website And Frontend Management

Objective: Implement the complete sectional public website and the administration tools needed to manage marketing content safely.

Scope:

- Vue/Inertia public website.
- Section-based landing page.
- Multiple hero slides.
- Opening-hours or information banner beneath the hero.
- About section.
- Services section.
- Departments section.
- Featured-doctor section.
- Testimonials.
- Appointment CTA.
- Other standard hospital calls to action.
- Contact/location information.
- Footer configuration.
- Section enable/disable.
- Section ordering.
- Draft/preview/publish behaviour.
- Media management.
- Admin editing.
- Authorization.
- Audit logging.
- Responsive visual design.
- Preservation or faithful reconstruction of the former professional style where reference material exists.

Boundaries:

- CMS marketing content may use validated structured JSON where appropriate.
- Core hospital, staff, clinical, and financial data must remain relational.
- Services displayed for marketing must not automatically become clinical or billable service records.
- Featured doctors should eventually reference real staff/doctor profiles rather than duplicate authentication identities.

Dependencies:

- Phase 1A hospital, staff, roles, audit, settings, and public-site defaults.

## Phase 2: Patient Registration, Appointments, Queues, and Encounters

Objective: Support front desk and basic outpatient clinical workflow.

Modules/workflows:

- Patient registration.
- Patient search.
- Appointments.
- Walk-ins.
- Queue management.
- Basic encounter/vitals/clinical notes.

Dependencies:

- Facilities, users, roles, audit, numbering.

Database work:

- Patients, contacts, next of kin, identifiers, alerts, allergies, appointments, visits, queues, encounters, vitals, notes, diagnoses.

Backend work:

- Patient registration action.
- Duplicate detection rules.
- Appointment scheduling and queue actions.
- Encounter start/sign-off/amendment workflow.

Interface work:

- Patient registration/search.
- Reception queue.
- Doctor worklist.
- Encounter screen.

Authorization work:

- Receptionist, doctor, nurse access boundaries.
- Facility-scoped patient access.

Tests:

- Patient numbering, duplicate detection, queue transitions, encounter authorization, audit.

Documentation:

- Front desk and doctor workflow guide.

Acceptance criteria:

- A clinic can register a patient, book/check in a visit, capture vitals, document a basic encounter, and view history.

Risks:

- Clinical note amendment must avoid silent overwrite.

Deferred:

- Admissions, complex specialty templates, patient portal.

Implementation sessions:

1. Patient core schema and registration.
2. Patient search and duplicate warnings.
3. Appointments and schedules.
4. Queue and check-in.
5. Vitals.
6. Basic encounter notes/diagnoses.
7. Encounter sign-off/amendment audit.

## Phase 3: Billing, Payments, Service Catalogue, and Cashier Operations

Objective: Make services billable and payments traceable.

Modules/workflows:

- Service catalogue.
- Price lists.
- Invoices.
- Payments.
- Receipts.
- Deposits.
- Cashier shifts.

Dependencies:

- Patients, visits, roles, audit, numbering.

Database work:

- Service items, prices, invoices, invoice items, payments, receipts, refunds, discounts, cashier shifts, ledger entries.

Backend work:

- Invoice generation.
- Payment posting.
- Receipt numbering.
- Discount/refund approval.
- Ledger and reconciliation foundations.

Interface work:

- Cashier worklist.
- Patient account view.
- Receipts and daily collections.

Authorization work:

- Cashier/accountant separation.
- Approval permissions for discounts/refunds/voids.

Tests:

- Billing calculations, payment allocation, receipt immutability, cashier shift reports.

Documentation:

- Billing/cashier SOP.

Acceptance criteria:

- A patient can be billed, pay, receive a receipt, and appear in daily collection reports.

Risks:

- Financial records must use reversals/voids, not destructive edits.

Deferred:

- Insurance claims and payment gateways.

Implementation sessions:

1. Service catalogue and price list.
2. Invoice model and manual invoice creation.
3. Payment and receipt posting.
4. Patient account ledger.
5. Cashier shifts.
6. Discounts/refunds approval.
7. Billing reports.

## Phase 4: Laboratory and Radiology

Objective: Support diagnostic requests, controlled result entry, and report delivery.

Modules/workflows:

- Lab catalogue.
- Lab requests/specimens/results/approval.
- Imaging catalogue/requests/reports/approval.

Dependencies:

- Patients, encounters, billing, audit, notifications.

Database work:

- Lab tests, requests, specimens, results, approvals, reference ranges.
- Imaging studies, requests, schedules, reports, approvals, attachments.

Backend work:

- Request ordering.
- Sample/specimen tracking.
- Result entry and verification.
- Critical-result flags.
- Report generation.

Interface work:

- Doctor ordering.
- Lab/radiology worklists.
- Result entry and approval.
- Patient/encounter result views.

Authorization work:

- Separation of requester, result entrant, approver where required.

Tests:

- Lab approval, critical flags, report access, billing integration.

Documentation:

- Lab/radiology workflow guide.

Acceptance criteria:

- A doctor can request tests, lab/radiology staff can process and approve results, and results return to the patient record.

Risks:

- Reference ranges, critical alerts, and approval workflows require professional validation.

Deferred:

- Device integrations, PACS/DICOM.

Implementation sessions:

1. Diagnostic catalogues.
2. Lab request and specimen workflow.
3. Lab result entry.
4. Lab approval and reports.
5. Imaging request workflow.
6. Imaging reports/attachments.
7. Diagnostic turnaround reports.

## Phase 5: Pharmacy, Inventory, and Procurement

Objective: Support prescribing, dispensing, stock control, and procurement.

Modules/workflows:

- Medicine catalogue.
- Prescriptions.
- Dispensing.
- Inventory batches.
- Stock receipts/transfers/adjustments.
- Suppliers and purchase orders.

Dependencies:

- Patients, encounters, billing, audit, facilities.

Database work:

- Medicines, prescriptions, dispenses, inventory items, batches, movements, suppliers, POs, GRNs.

Backend work:

- Transactional dispensing with stock decrement.
- Batch/expiry tracking.
- Inventory movement ledger.
- Reorder alerts.

Interface work:

- Pharmacy worklist.
- Dispensing screen.
- Stock management screens.
- Procurement screens.

Authorization work:

- Pharmacist/storekeeper/accountant separation.
- Controlled-drug permissions.

Tests:

- Dispensing, stock movement, expiry, partial dispensing, returns, audit.

Documentation:

- Pharmacy and inventory SOP.

Acceptance criteria:

- Prescriptions can be dispensed against stock with traceable batch movements.

Risks:

- Stock and billing must remain consistent under concurrent use.

Deferred:

- Drug interaction integration, advanced procurement analytics.

Implementation sessions:

1. Medicine and inventory catalogue.
2. Stock receipt and batch tracking.
3. Prescriptions.
4. Dispensing and partial dispensing.
5. Stock transfers/adjustments/returns.
6. Procurement.
7. Reorder and expiry reports.

## Phase 6: Admissions, Wards, Beds, and Nursing

Objective: Support inpatient workflow.

Modules/workflows:

- Admission requests.
- Bed allocation.
- Ward transfers.
- Nursing notes.
- MAR.
- Observation charts.
- Discharge.

Dependencies:

- Patients, encounters, billing, pharmacy, facilities.

Database work:

- Wards, rooms, beds, admissions, allocations, transfers, nursing records, MAR, discharge summaries.

Backend work:

- Bed availability.
- Admission status workflow.
- Transfer/discharge actions.

Interface work:

- Ward boards.
- Nurse worklists.
- Admission/discharge screens.

Authorization work:

- Nurse, doctor, cashier clearance boundaries.

Tests:

- Bed allocation conflicts, transfers, MAR audit, discharge clearance.

Documentation:

- Inpatient workflow guide.

Acceptance criteria:

- A patient can be admitted, assigned a bed, monitored, transferred, and discharged with final billing.

Risks:

- Nursing and medication workflows need clinical validation.

Deferred:

- Advanced charting and specialty inpatient templates.

Implementation sessions:

1. Ward/room/bed setup.
2. Admission requests.
3. Bed allocation.
4. Nursing assessments/notes.
5. MAR and observation charts.
6. Ward transfers.
7. Discharge summary and billing clearance.

## Phase 7: Blood Bank and Transfusion

Objective: Implement safe blood-bank inventory and transfusion chain of custody.

Dependencies:

- Inventory concepts, patients, lab, admissions/encounters, audit.

Acceptance criteria:

- Only after blood-bank professionals approve workflows, statuses, labels, testing, quarantine/release, cross-match, issue, transfusion, and reaction reporting.

Risks:

- Safety-critical. Do not implement from assumptions.

Implementation sessions:

1. Professional workflow validation.
2. Donor and donation records.
3. Blood unit identification and testing.
4. Quarantine/release/storage.
5. Cross-match/reservation.
6. Issue/return.
7. Transfusion administration/reactions/haemovigilance.

## Phase 8: Insurance, Corporate Accounts, and Claims

Objective: Support HMO/corporate billing after cash billing is stable.

Dependencies:

- Billing, patients, encounters, diagnostics, pharmacy.

Implementation sessions:

1. Insurers/corporates/plans.
2. Tariffs.
3. Eligibility/pre-authorisation.
4. Claims.
5. Claim batches.
6. Rejections/resubmissions.
7. Receivables ageing.

## Phase 9: Reporting, Notifications, Integrations, and Commercial Administration

Objective: Add commercial operating capabilities.

Modules/workflows:

- Dashboards.
- Exports.
- Email/SMS reminders.
- Payment gateway integration.
- Accounting export/integration.
- Platform licences/subscriptions.
- Backup monitoring.

Implementation sessions:

1. Reporting data definitions.
2. Operational dashboards.
3. Financial reports.
4. Clinical activity reports.
5. Notification templates and delivery logs.
6. Payment/accounting integrations.
7. Licence/subscription administration.

## Phase 10: Security Hardening, Performance, Deployment, Documentation, and Release Preparation

Objective: Prepare for pilot, production, and commercial scale.

Work:

- Threat modeling.
- Authorization/IDOR testing.
- Tenant/facility isolation tests.
- Backup and restore drills.
- Performance testing.
- Accessibility and mobile responsiveness testing.
- Deployment automation.
- Runbooks and user manuals.
- Nigeria Data Protection Act-aligned technical controls, with legal review.

Acceptance criteria:

- Pilot-ready release checklist is complete.
- Production deployment is repeatable.
- Backups are restorable.
- Critical workflows pass tests.

## First Recommended Implementation Milestone

Phase 0, Session 1-4:

1. Put the project under source control or confirm the real remote repository.
2. Fix local PHP extension requirements.
3. Repair broken auth/logout routing.
4. Align `users` schema, factory, auth views, admin views, and tests.

This milestone should be completed before adding any hospital module.

## Phase 1B: Sectional Public Website And Publishing Module

Status: implemented in this branch pending final verification and local commit.

Delivered scope:

- Sectional public website managed through Laravel/Inertia/Vue.
- Draft, preview, publish, unpublish, and revision restore workflow.
- Public Website administration area with page, section, item, media, and revision controls.
- Public pages for home, about, services, departments, doctors, doctor profile, news, article view, contact, appointment information, and policies.
- Safe media upload controls and placeholder licensing documentation.
- Explicit deferral of patient, clinical, appointment-booking, billing, lab, pharmacy, inventory, admissions, blood bank, theatre, and insurance/HMO modules.

Recommended next milestone: Phase 2 appointment/contact intake should begin by connecting the existing informational appointment CTA to a controlled, non-clinical appointment request workflow after the hospital approves required fields, consent copy, and triage boundaries.
