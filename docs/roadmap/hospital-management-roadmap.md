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

## Phase 4A: Laboratory Catalogue, Requests, Specimens, Results And Approval

Status: implemented on 2026-08-23.

Delivered scope:

- Hospital/facility-scoped laboratory catalogue with specimen types, units, tests, components/analytes and panels.
- Configurable reference ranges and critical thresholds, with seeded structural examples only.
- Optional mapping from lab tests to Phase 3A billable services.
- Lab requests linked to patient, visit, encounter and ordering clinician.
- Request, accession and specimen numbers through `NumberSequenceService`.
- Specimen collection, receipt, rejection, recollection and chain-of-custody events.
- Lab worklist, request-processing screen and printable approved report.
- Numeric, text, qualitative and comment result entry with reference-range snapshots.
- Draft, verification, approval, release, critical acknowledgement and append-only amendment workflows.
- Approved-result visibility in patient/encounter timeline foundation.
- Billing integration through the server-side invoice workflow.
- Permissions, policies, hospital scoping, IDOR protection, lab events and audits.

Deferred:

- Pharmacy, inventory, admissions, device integration, stock consumption, PACS/DICOM and fabricated medical catalogues.

## Phase 4B: Radiology Requests, Scheduling, Reports And Secure Attachments

Status: implemented on 2026-08-23.

Delivered scope:

- Hospital/facility-scoped radiology modality and study catalogue.
- Optional radiology study mapping to Phase 3A billable services.
- Radiology requests linked to patient, visit, encounter and ordering clinician.
- Request and accession numbering through `NumberSequenceService`.
- Scheduling by facility, room, equipment and assigned staff with conflict prevention.
- Ordered, scheduled, arrived, performed, reporting, verified, approved, released and cancelled workflows.
- Structured draft reporting, verification, approval, release and printable approved report.
- Critical-finding communication, acknowledgement and escalation history.
- Append-only amendments after report approval/release.
- Approved/released report visibility in the patient and encounter timeline.
- Billing integration through `InvoiceWorkflowService`.
- Private support attachments with validation, quarantine, clearance, authorized download and retirement controls.
- Permissions, policies, hospital scoping, IDOR protection, radiology events and audit events.
- Responsive Inertia/Vue catalogue, request, scheduling, worklist, report-entry and printable-report screens.

Deferred:

- PACS/DICOM, device integration, radiology protocol engines, contraindication engines, pharmacy, inventory and admissions.

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

## Phase 5A: Medicine Catalogue, Inventory Locations, Batches And Stock Ledger

Status: implemented on 2026-08-24.

Delivered scope:

- Hospital/facility-scoped inventory locations.
- Units of measure and controlled pack/base-unit conversion factors.
- Medicine and practical non-medicine inventory items with unique SKU and optional barcode.
- Batch/lot records with manufacture date, expiry date, supplier reference, state and unit-cost snapshot.
- Authorized opening balances through immutable stock movements.
- Transactional stock balances by location, item and batch.
- Transfer request, dispatch, receipt and cancellation workflow.
- Adjustment requests with approval separation.
- Reversal movements for corrections.
- Negative-stock prevention.
- Batch state management for quarantine, available, expired, damaged, recalled and exhausted.
- Low-stock, near-expiry, expired-stock and FEFO reports.
- Permissions, policies, role-aware navigation, scoping, audit events and responsive Inertia/Vue screens.

Deferred:

- Prescriptions, dispensing, procurement, supplier purchase orders, supplier master data, valuation reporting and automated expiry jobs.

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

## Phase 1B.1: Public Website UI/UX And Theme-System Overhaul

Status: implemented in this branch pending final verification and local commit.

Delivered scope:

- Token-driven public design system.
- Light, dark, and system appearance preferences.
- Calm, Healing, Alert, Blood, and Seagrass accent themes.
- Visitor theme persistence and early document initialization.
- Admin-managed draft/published public theme defaults.
- Centered hero and major section introductions.
- Redesigned hero-to-about information band.
- Two-column homepage services accordion backed by published marketing-service records.
- Upgraded public page heroes, cards, CTAs, footer, mobile navigation, and theme controls.
- Phase 1B CMS, publishing, preview, permissions, auditing, media, and SEO architecture preserved.

Deferred:

- Operational appointment intake remains Phase 2.
- Patients, clinical records, billing, lab, pharmacy, inventory, admissions, blood bank, theatre, insurance/HMO, and other Phase 2+ modules remain unimplemented.

## Phase 1B.2: Public Website Publishing Correctness

Status: implemented on 2026-08-22.

Delivered scope:

- Additive draft/published fields for public page titles and SEO, section labels/order/visibility, and item section placement/type/slug/title/summary/visibility/feature/order.
- Public rendering reads published snapshots only; draft editing no longer changes public-visible content until publish.
- Item publish and unpublish workflow with authorization, revision records, and audit logging.
- Published doctor and article detail pages render item body and media.
- Public brand, tagline, footer badges, and major homepage headings use hospital settings or CMS-managed content instead of hardcoded copy.
- SEO canonical metadata normalized to `canonical_url`.
- Legacy CMS `/admin/pages` remains preserved as a quarantined archive; active public-site management is `/admin/public-website`.

Deferred:

- Patient, appointment, clinical, billing, laboratory, pharmacy, inventory, admissions, blood bank, theatre, and insurance/HMO modules remain unimplemented.
- Rich field-specific public-site editors remain future work; structured JSON editing is still the primary content-entry surface for many section/item fields.

## Phase 1B.3: User-Friendly Public Website Management

Status: implemented on 2026-08-22.

Delivered scope:

- Structured Vue administration forms for public branding, header/navigation, hero slides, information banner, about, services, departments, clinicians, trust items, testimonials, CTA, news/articles, contact/location, footer, SEO, and theme defaults.
- Repeatable content controls for adding, removing, enabling, disabling, and reordering draft content without requiring JSON knowledge.
- Reusable media picker with preview, alternative text, and upload support.
- Automatic media usage derivation from draft and published public-site payloads, with delete protection for referenced assets.
- Superadmin-only read-only diagnostics view for underlying payloads.
- Phase 1B.2 draft, preview, publish, unpublish, revision, authorization, and audit boundaries preserved.

Deferred:

- Patient, appointment, clinical, billing, lab, pharmacy, inventory, admissions, blood bank, theatre, insurance/HMO, and other Phase 2+ modules remain unimplemented.

## Phase 2A: Patient Registration And Identity Foundation

Status: implemented on 2026-08-23.

Delivered scope:

- Hospital-scoped patient registration with concurrency-safe hospital number allocation through the existing numbering sequence service.
- Demographics, optional identifiers, contacts, next of kin, allergies and important alerts.
- Protected exact lookup for phone, email and identifiers using encrypted display values plus deterministic hashes.
- Duplicate warnings based on configurable demographic, phone and identifier signals with no auto-merge.
- Active, archived and deceased patient states with controlled, audited transitions and no hard deletion.
- Patient list, registration form and profile pages using Inertia/Vue.
- Patient search, facility/hospital scoping, policies, permissions, audit events and activity-timeline foundation.

Deferred:

- Patient photographs and documents until private storage, scanning and access logging are designed.
- Patient merging, appointments, queues, encounters, billing, laboratory, pharmacy and admissions.

## Phase 2B: Appointments, Walk-Ins, Check-In And Queues

Status: implemented on 2026-08-23.

Delivered scope:

- Clinician working schedules, breaks, unavailability, appointment types and hospital-timezone availability generation.
- Staff booking for registered patients with transactional conflict prevention.
- Confirm, reschedule, cancel and no-show workflows with reasons, event history and audit logging.
- Public appointment request form connected to the public CTA, limited to non-clinical contact/preferences, with rate limiting, consent, spam protection, encrypted contact storage and lookup hashes.
- Staff review workflow for public requests without automatic patient or appointment creation.
- Visit foundation for appointment check-in and walk-ins.
- Daily facility/department queues with queue numbers, waiting/call/recall/transfer/skip/remove/priority actions, reasons, history and audit logging.
- Reception appointment view, queue board, role-aware navigation, policies, permissions, hospital/facility scoping and IDOR protection.

Deferred:

- Encounters, clinical notes, medical triage, billing, laboratory, pharmacy and admissions.
- Automated patient merge and rich calendar drag/drop scheduling.

## Phase 2C: Outpatient Vitals And Clinical Encounters

Status: implemented on 2026-08-23.

Delivered scope:

- Encounters linked to patients, visits, appointment/walk-in source, facility, department, queue entry and responsible clinician.
- Clinician worklist for checked-in and queued patients.
- Start, pause, resume, sign and cancel workflows with transactionally coordinated visit, queue and appointment state updates.
- Nursing/authorized staff vital recording with BMI calculation.
- Clinical assessment fields for complaint, histories, examination, diagnoses, plan, follow-up and referral recommendation.
- Signed encounter immutability with append-only amendments.
- Patient clinical timeline combining encounters, vitals, allergies and alerts.
- Prominent allergy/alert display, policies, permissions, validation, audit events, scoping and tests.

Deferred:

- Billing, prescriptions, laboratory, radiology, pharmacy, admissions, full referral module, normal-range interpretation and diagnostic catalogues.

## Phase 3A: Service Catalogue, Pricing And Invoicing Foundation

Status: implemented on 2026-08-23.

Delivered scope:

- Hospital-scoped billable service categories and services with unique codes, department links, facility availability, active state and optional public-service mapping.
- Default and facility-specific price history in minor units, with effective dates, currency and overlap protection.
- Configurable tax exemption, tax basis points and discount eligibility without hardcoded country tax rules.
- Draft invoices linked to patient, visit and encounter.
- Server-calculated invoice lines with service snapshots, quantity, unit price, discounts, tax and totals.
- Manual authorized lines with mandatory reason.
- Invoice number allocation through `NumberSequenceService` at issue.
- Draft, issued, cancelled, voided and replacement-draft workflows with audit/history and issued-invoice immutability.
- Patient invoice history foundation, Inertia admin screens, policies, permissions, scoping and tests.

Deferred:

- Payments, receipts, cashier shifts, insurance/HMO, laboratory, pharmacy and inventory.

## Phase 3B: Payments, Receipts, Cashier Shifts And Reconciliation

Status: implemented on 2026-08-23.

Delivered scope:

- Hospital-scoped payment methods for cash, transfer, POS/card and other approved methods.
- Cashier shifts with opening float, cash collections, expected cash, counted cash, variance, close and supervisor review.
- Payments linked to patient, facility, cashier and open cash shift where required.
- Receipt numbering through `NumberSequenceService` with hospital-scoped idempotency protection.
- Partial and multi-invoice allocation, patient deposits/unallocated credit and later allocation.
- Server-derived invoice paid amount, balance and unpaid/part-paid/paid status.
- Payment reversal, refund request/approval/rejection/processing, approval separation and audit history.
- Cashier workbench, printable receipt page and accounting review/summary screens.

Deferred:

- Insurance/HMO, payment gateways, laboratory, pharmacy, inventory and full accounting integrations.
