# Data Model Plan

Date: 2026-08-14

## Existing Schema Inventory

Existing tables from migrations:

- `users`
- `password_reset_tokens`
- `sessions`
- `cache`
- `cache_locks`
- `jobs`
- `job_batches`
- `failed_jobs`
- `permissions`
- `roles`
- `model_has_permissions`
- `model_has_roles`
- `role_has_permissions`
- `pages`
- `sections`
- `blocks`

All migrations are marked as run by `php artisan migrate:status`.

## Existing Tables Assessment

### users

Current columns include:

- `id`
- `firstname`
- `lastname`
- `email`
- `email_verified_at`
- `password`
- `access_level`
- `remember_token`
- timestamps

Issues:

- Factory still writes `name`, causing test failures.
- Admin dashboard/layout still read `Auth::user()->name`.
- `access_level` duplicates role semantics and can drift from Spatie roles.
- No staff profile, employment metadata, license number, phone, status, facility membership, last login, MFA, or password policy fields.
- No tenant/hospital/facility scope.
- `User::$guarded = []` is risky.

### Spatie permission tables

Issues:

- Good starting point for RBAC.
- Teams are disabled, so roles are not scoped per hospital/facility.
- Current permission list is too coarse and placeholder-like.
- No policies use these permissions.

### pages, sections, blocks

Issues:

- CMS schema is generic and marketing-oriented.
- Models lack relationships used by controllers and seeders.
- `sections.content` uses JSON; acceptable for marketing content, not for clinical/financial core data.
- No publishing/audit workflow.

### cache, sessions, jobs

Issues:

- Standard Laravel infrastructure tables.
- Useful for production, but queue processing strategy and scheduler are not configured beyond defaults.

## Missing Constraints and Indexes

The current hospital domain is not present, so nearly all future constraints are missing. For existing tables:

- `users.email` is unique.
- `pages.slug` is unique.
- `sections.page_id` and `blocks.section_id` are foreign keys with cascade deletes.
- No tenant/facility keys exist.
- No audit fields such as `created_by`, `updated_by`, `voided_by`, or amendment history exist.

## Risky Design Areas

- Clinical and financial records must not rely on destructive deletes.
- Tenant/facility scoping cannot be retrofitted casually after many modules exist.
- Core clinical data should be relational, not hidden in JSON blobs.
- Status fields must be consistent and controlled by allowed transitions.
- Numbering sequences must be tenant/facility-aware.
- Files must be stored outside public access and served through authorization checks.

## Personally Identifiable and Clinical Data Needing Protection

Future protected fields include:

- Patient demographics, contacts, identifiers, photos.
- Next of kin and emergency contacts.
- Allergies, diagnoses, clinical notes, vitals, lab results, imaging reports, prescriptions, admissions.
- Payment and insurance data.
- Staff records and professional identifiers.
- Attachments and consent records.
- Audit logs and access history.

Use encryption selectively for fields that require it, but do not encrypt fields that must be indexed/searched without a clear design.

## Proposed High-Level Entity Model

### Platform and Tenant Foundations

- `hospitals`
  - tenant/customer record, legal name, display name, status, subscription/licence status, default timezone, country, contact details.
- `facilities`
  - hospital branch/site, address, facility type, active status.
- `departments`
  - department/service unit, hospital/facility scope, clinical/administrative category.
- `hospital_settings`
  - branding, numbering formats, billing settings, tax settings, workflow flags.
- `number_sequences`
  - hospital/facility scoped prefixes and next numbers.
- `subscriptions` or `licences`
  - commercial entitlement and validity, depending on deployment model.

### Identity and Access

- `users`
  - authentication identity.
- `staff_profiles`
  - staff-specific profile linked to user.
- `facility_user`
  - user membership in one or more facilities.
- Spatie `roles` and `permissions`
  - preferably scoped or constrained through hospital/facility assignment design.
- `user_status_events`
  - activation, suspension, invitation, password/security events where needed.

### Patients

- `patients`
  - hospital/facility scoped patient identity and hospital number.
- `patient_contacts`
- `patient_next_of_kin`
- `patient_identifiers`
- `patient_photos` or attachment reference.
- `patient_alerts`
- `patient_allergies`
- `patient_conditions`
- `patient_documents`
- `patient_consents`
- `patient_merge_requests`
- `patient_status_events`

### Appointments and Queue

- `doctor_schedules`
- `appointments`
- `appointment_status_events`
- `visits`
- `queues`
- `queue_entries`

### Encounters and EMR

- `encounters`
- `vital_signs`
- `clinical_notes`
- `diagnoses`
- `procedures`
- `treatment_plans`
- `encounter_attachments`
- `referrals`
- `encounter_signoffs`
- `clinical_amendments`

### Admissions, Wards, and Nursing

- `wards`
- `rooms`
- `beds`
- `admission_requests`
- `admissions`
- `bed_allocations`
- `ward_transfers`
- `nursing_assessments`
- `nursing_notes`
- `care_plans`
- `medication_administration_records`
- `observation_charts`
- `doctor_rounds`
- `discharge_summaries`

### Laboratory

- `lab_tests`
- `lab_test_groups`
- `lab_test_panels`
- `lab_reference_ranges`
- `lab_requests`
- `lab_request_items`
- `specimens`
- `specimen_events`
- `lab_results`
- `lab_result_items`
- `lab_result_approvals`
- `critical_result_notifications`

### Radiology

- `imaging_studies`
- `imaging_requests`
- `imaging_request_items`
- `imaging_schedules`
- `imaging_reports`
- `imaging_report_approvals`
- `imaging_attachments`

### Pharmacy and Inventory

- `medicines`
- `medicine_generics`
- `dosage_forms`
- `medicine_strengths`
- `prescriptions`
- `prescription_items`
- `prescription_reviews`
- `dispenses`
- `dispense_items`
- `inventory_items`
- `inventory_locations`
- `inventory_batches`
- `stock_movements`
- `stock_adjustments`
- `stock_transfers`
- `stock_receipts`
- `stock_returns`
- `suppliers`
- `purchase_requests`
- `purchase_orders`
- `goods_received_notes`
- `controlled_drug_register`

### Billing and Payments

- `service_catalogue_items`
- `price_lists`
- `patient_accounts`
- `invoices`
- `invoice_items`
- `payments`
- `receipts`
- `refunds`
- `discount_requests`
- `cashier_shifts`
- `reconciliations`
- `account_ledger_entries`

### Insurance and Corporate Accounts

- `insurers`
- `corporate_accounts`
- `benefit_plans`
- `plan_tariffs`
- `eligibility_checks`
- `pre_authorisations`
- `claims`
- `claim_items`
- `claim_batches`
- `claim_rejections`
- `claim_resubmissions`
- `receivables`

### Blood Bank and Transfusion

- `blood_donors`
- `donor_screenings`
- `blood_donations`
- `blood_units`
- `blood_components`
- `blood_unit_tests`
- `blood_unit_status_events`
- `blood_storage_locations`
- `cross_matches`
- `blood_reservations`
- `blood_issues`
- `blood_returns`
- `transfusion_requests`
- `transfusion_administrations`
- `transfusion_reactions`
- `haemovigilance_events`

This area requires qualified blood-bank and clinical validation before schema finalization.

### Theatre and Procedures

- `theatres`
- `procedure_catalogue`
- `theatre_schedules`
- `procedure_bookings`
- `pre_op_checklists`
- `theatre_teams`
- `procedure_consumables`
- `procedure_notes`
- `post_op_notes`
- `recovery_observations`

### Reporting, Audit, Attachments, and Notifications

- `audit_events`
- `audit_event_metadata`
- `attachments`
- `attachment_access_logs`
- `notifications`
- `notification_templates`
- `notification_deliveries`
- `exports`
- `export_access_logs`
- `backup_runs`
- `restore_tests`

## Stakeholder Decisions Required Before Final Migrations

- Tenant model: dedicated deployments first, shared SaaS, or hybrid.
- Whether a user can belong to multiple hospitals or only multiple facilities within one hospital.
- Patient numbering rules per hospital/facility.
- Whether patients can share records across branches of the same hospital.
- Required demographic fields for Nigerian hospitals/clinics.
- Clinical note structure: free text, templates, specialty-specific forms, or hybrid.
- Billing timing: pay-before-service, pay-after-service, deposits, mixed workflows.
- Lab approval levels and critical result escalation.
- Pharmacy substitution and controlled-drug rules.
- Blood-bank workflow and chain-of-custody requirements.
- Insurance/HMO claim format and local payer requirements.
- Retention and archival policy.
- Data import requirements from existing hospital records.
