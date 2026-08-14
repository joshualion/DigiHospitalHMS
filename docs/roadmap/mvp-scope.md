# MVP Scope

Date: 2026-08-14

## Repository-Based Finding

The current repository has no usable hospital operations backend. The MVP cannot be formed by polishing existing modules; it must be built after Phase 0 recovery and Phase 1 foundations.

## Demo-Ready

A demo-ready version can show controlled workflows with limited real-world risk. It should include:

- Working authentication and logout.
- Hospital/facility setup.
- Staff roles and permissions.
- Patient registration and search.
- Appointment booking and queue.
- Basic encounter notes and vitals.
- Basic billing invoice/payment/receipt.
- Basic lab request/result approval.
- Basic pharmacy catalogue/prescription/dispensing.
- Essential dashboards with seeded or real demo data.
- Audit log visible to administrators.

Demo-ready does not mean safe for live clinical use.

## Pilot-Ready

Pilot-ready for a small clinic/hospital should include:

- All demo-ready features using real data.
- Facility-scoped authorization.
- Tested patient numbering.
- Duplicate patient warnings.
- Non-destructive clinical note amendments.
- Billing ledger and cashier shifts.
- Lab result approval and critical-result handling decisions.
- Pharmacy stock batch/expiry and dispensing audit.
- Basic inventory receipts and adjustments.
- Backups and restore documentation.
- Deployment documentation.
- Role-specific navigation.
- Audit logs for clinical, billing, user, permission, and export actions.
- Mobile-responsive front desk, doctor, cashier, lab, and pharmacy workflows.

## Production-Ready

Production-ready requires:

- Threat model and security hardening.
- `APP_DEBUG=false` and deployment-specific configuration.
- HTTPS/TLS configuration.
- Optional MFA plan or implementation.
- Tested backup/restore.
- Disaster recovery plan.
- Authorization and IDOR tests.
- Performance tests with realistic data volumes.
- Document storage controls with signed/temporary access.
- Export restrictions and audit.
- User manuals and admin runbooks.
- Legal/privacy review for Nigeria Data Protection Act obligations.
- Clinical validation for safety-critical workflows.

## Commercially Scalable

Commercial scale requires:

- Repeatable deployment automation.
- Upgrade/migration strategy.
- Licence/subscription administration.
- Support access controls.
- Monitoring and error tracking.
- Structured release notes.
- Tenant-isolation test suite if SaaS is introduced.
- Data migration tools.
- Customer-specific configuration without forks.

## Recommended MVP Modules

Minimum product that can honestly be piloted:

1. Identity and access
   - Users, staff profiles, roles, permissions, facility memberships.
2. Hospital and facility settings
   - Hospital profile, branches/facilities, departments, numbering sequences.
3. Audit and compliance foundation
   - Audit events for protected actions.
4. Patient management
   - Registration, demographics, contacts, next of kin, allergies, alerts, search, visit history.
5. Appointments and queue
   - Booking, walk-ins, check-in, doctor worklists, cancellation/reschedule basics.
6. Encounters
   - Vitals, complaints, clinical notes, diagnoses, treatment plan, sign-off/amendment.
7. Billing and payments
   - Service catalogue, invoices, payments, receipts, deposits, cashier shifts, daily collections.
8. Basic laboratory
   - Test catalogue, requests, specimens, results, approval, printable report.
9. Basic pharmacy and inventory
   - Medicine catalogue, prescription, dispensing, stock batches, receipts, adjustments, expiry/reorder reports.
10. Essential reports
   - Patient attendance, daily collections, outstanding balances, lab activity, pharmacy stock/expiry, audit reports.
11. Deployment and backup documentation
   - Installation, environment, migration, backup, restore, upgrade notes.

## Deferred From MVP

- Full admissions/inpatient nursing.
- Blood bank and transfusion.
- Theatre management.
- Insurance/HMO claims.
- Advanced procurement.
- Patient portal/mobile app.
- Payment gateway integration.
- Accounting integration.
- Lab-device integration.
- PACS/DICOM integration.
- Full SaaS shared multi-tenancy.
- Advanced specialty modules such as maternity, dental, dialysis, mortuary.

## Why These Items Are Deferred

The current repository has no domain foundation. Building too many safety-critical modules at once would create untested clinical and financial risk. Blood bank, medication administration, admissions, and claims require stakeholder validation and stronger workflow foundations.

## MVP Acceptance Criteria

- A patient can be registered once with a unique hospital number.
- Staff can log in and see only authorized role/facility workflows.
- A receptionist can book or check in a patient.
- A doctor/nurse can document a basic encounter and vitals.
- A cashier can bill and receive payment with immutable receipts.
- A lab scientist can process and approve a basic lab result.
- A pharmacist can dispense from stock with batch/expiry traceability.
- Administrators can view audit logs and essential reports.
- Backups can be taken and restored in a test environment.
- Core workflows have automated feature tests.

## First Sale Boundary

Do not sell as production hospital software until:

- Auth, roles, facility scoping, and audit are complete.
- Patient, appointment, encounter, billing, lab, and pharmacy workflows are tested.
- Backup/restore is proven.
- Security review is complete.
- Clinical and financial stakeholders have validated the workflows.
