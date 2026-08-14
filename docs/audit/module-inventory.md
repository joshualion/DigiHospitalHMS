# Module Inventory

Date: 2026-08-14

Status key: Complete, Partially implemented, Interface only, Backend only, Placeholder, Broken, Not started, Unable to determine.

## Summary

No module qualifies as Complete. The repository contains only scaffolding-level authentication, roles, public marketing pages, an admin shell, and a broken CMS attempt. Core hospital operations are not implemented.

## Authentication and Profile

- Status: Broken
- Screens:
  - `resources/views/livewire/pages/auth/register.blade.php`
  - `resources/views/livewire/pages/auth/login.blade.php`
  - `resources/views/livewire/pages/auth/forgot-password.blade.php`
  - `resources/views/livewire/pages/auth/reset-password.blade.php`
  - `resources/views/livewire/pages/auth/verify-email.blade.php`
  - `resources/views/livewire/pages/auth/confirm-password.blade.php`
  - `resources/views/profile.blade.php`
  - `resources/views/livewire/profile/*`
- Routes:
  - `register`, `login`, `forgot-password`, `reset-password/{token}`, `verify-email`, `confirm-password`, `logout`, `profile`
- Controllers/actions:
  - `App\Http\Controllers\Auth\VerifyEmailController`
  - `App\Livewire\Forms\LoginForm`
  - `App\Livewire\Actions\Logout`
  - Broken reference to missing `App\Http\Controllers\Auth\AuthenticatedSessionController`
- Models:
  - `App\Models\User`
- Database tables:
  - `users`, `password_reset_tokens`, `sessions`
- Validation:
  - Login validates email/password and rate limits.
  - Registration validates first name, last name, email, password confirmation.
  - Profile forms are scaffolded but need schema verification.
- Authorization:
  - Session guard only.
- Business logic:
  - Basic Laravel auth flows only.
- Tests:
  - Breeze auth/profile tests exist but fail.
- Missing requirements:
  - Working logout route.
  - Consistent `firstname`/`lastname` handling across tests, views, factories, and dashboards.
  - Healthcare-grade session policy, optional MFA, staff invitation/onboarding, password policy decisions.
- Major risks:
  - Tests are misleading because they target the old `name` schema.
  - Route list fails due to missing controller.
  - `User::$guarded = []` is unsafe for future sensitive fields.

## Identity, Roles, and Permissions

- Status: Backend only
- Screens:
  - Admin `users` and `roles` routes exist, but `resources/views/admin/users.blade.php` and `resources/views/admin/roles.blade.php` are missing.
- Routes:
  - `/admin/users`
  - `/admin/roles`
  - Admin route group uses `role:superadmin|admin`.
- Controllers/actions:
  - None for users/roles.
- Models:
  - `User`
  - Spatie package models for roles and permissions.
- Database tables:
  - Spatie permission tables.
- Validation:
  - None for role/user administration.
- Authorization:
  - Spatie installed; only `role` middleware alias registered.
- Business logic:
  - Role/permission seeders only.
- Tests:
  - None for roles/permissions.
- Missing requirements:
  - Staff profiles, invitations, user status, role assignment UI, granular permission matrix, policies, audit logging.
- Major risks:
  - Broad static roles are not enough for commercial multi-hospital use.
  - No hospital/facility scoping.

## Public Website / Marketing Pages

- Status: Interface only
- Screens:
  - `frontend.home`, `frontend.about`, `frontend.blog`, `frontend.contact`
  - Missing: `frontend.doctor`, `frontend.appointment`, `frontend.policies`
- Routes:
  - `/`, `/about`, `/doctor`, `/appointment`, `/blog`, `/contact`, `/policies`
- Controllers/actions:
  - Mostly route closures.
  - `FrontendController::home()` exists but is not used by `routes/web.php`.
- Models:
  - Intended `Page`, `Section`, `Block`, but current home route bypasses them.
- Database tables:
  - `pages`, `sections`, `blocks` exist.
- Validation:
  - None.
- Authorization:
  - Public.
- Business logic:
  - None. Content is hardcoded in Blade.
- Tests:
  - Default homepage test passes because `/` returns a response.
- Missing requirements:
  - Real appointment booking, dynamic doctors, departments, services, content management, contact submissions.
- Major risks:
  - Hardcoded placeholder content may be mistaken for real modules.
  - External placeholder images and CDN script dependency are unsuitable for production healthcare deployments.

## Admin Dashboard

- Status: Placeholder / Broken
- Screens:
  - `resources/views/admin/dashboard.blade.php`
  - `resources/views/layouts/admin.blade.php`
- Routes:
  - `/admin/dashboard`
- Controllers/actions:
  - Route closure.
- Models:
  - None directly.
- Database tables:
  - None beyond authenticated user.
- Validation:
  - None.
- Authorization:
  - `auth` plus role `superadmin|admin`.
- Business logic:
  - None.
- Tests:
  - None.
- Missing requirements:
  - Role-specific dashboards, operational metrics, security/audit reports, navigation by module.
- Major risks:
  - References `Auth::user()->name`, but `users` has `firstname` and `lastname`.

## CMS Pages / Sections / Blocks

- Status: Broken
- Screens:
  - `resources/views/admin/pages/index.blade.php` placeholder
  - Missing `resources/views/admin/pages/edit.blade.php`
- Routes:
  - `/admin/pages`
  - `/admin/pages/{id}/edit`
  - `PUT /admin/pages/{id}`
- Controllers/actions:
  - `PageController@index`, `edit`, `update`
  - Empty `SectionController`, `BlockController`
- Models:
  - `Page`, `Section`, `Block`
- Database tables:
  - `pages`, `sections`, `blocks`
- Validation:
  - None. `PageController@update` accepts raw request data.
- Authorization:
  - Admin route group only; no permission checks like `manage pages`.
- Business logic:
  - Attempted nested update of page sections and blocks.
- Tests:
  - None.
- Missing requirements:
  - Model relationships, fillable/guarding, form requests, edit view, pagination, file uploads, content publishing workflow.
- Major risks:
  - `Page::all()->paginate(10)` is invalid.
  - Seeders call undefined relationships.
  - Controller calls undefined relationships.
  - No validation or audit trail.

## Hospital / Tenant / Facility Management

- Status: Not started
- Screens: none
- Routes: none
- Controllers/actions: none
- Models: none
- Database tables: none
- Validation: none
- Authorization: none
- Business logic: none
- Tests: none
- Missing requirements:
  - Hospital, branches/facilities, departments, service units, hospital-specific settings, branding, numbering, workflows, subscriptions/licenses.
- Major risks:
  - Current schema has no tenant or facility key, so no data isolation exists.

## Patient Management

- Status: Not started
- Screens/routes/controllers/models/tables/validation/authorization/business logic/tests: none
- Missing requirements:
  - Patient registration, hospital number, demographics, contacts, next of kin, allergies, chronic conditions, alerts, duplicate detection, documents, visit history, clinical timeline, consent records, merge controls.
- Major risks:
  - Must be designed before any clinical module to avoid weak patient identity and unsafe record linkage.

## Appointments and Front Desk

- Status: Interface only / Not started
- Screens:
  - `/appointment` route exists but view is missing.
  - Home page has a "Book Now" link.
- Routes:
  - `/appointment`
- Backend: none
- Missing requirements:
  - Appointment booking, walk-ins, doctor schedules, check-in, queue, cancellation, reminders, waiting-time tracking.
- Major risks:
  - Route exists without a screen or backend.

## Encounters and EMR

- Status: Not started
- Missing requirements:
  - Outpatient encounters, vitals, clinical notes, diagnoses, procedures, treatment plans, history, attachments, follow-up, referrals, sign-off, amendment history.
- Major risks:
  - Safety-critical clinical record design requires doctor/nurse validation before implementation.

## Admissions, Wards, Beds, and Nursing

- Status: Not started
- Missing requirements:
  - Admissions, bed allocation, transfers, nursing notes, MAR, observation charts, rounds, discharge summary, discharge billing clearance.
- Major risks:
  - Requires controlled statuses and non-destructive clinical history.

## Laboratory

- Status: Not started
- Missing requirements:
  - Test catalogue, requests, sample collection, specimen tracking, result entry, verification, reference ranges, abnormal flags, reports, external referrals.
- Major risks:
  - Result approval, critical result escalation, and reference-range handling require laboratory professional input.

## Radiology and Imaging

- Status: Not started
- Missing requirements:
  - Imaging catalogue, requests, scheduling, findings, reports, approval, attachments, external referrals, future PACS/DICOM boundary.
- Major risks:
  - File/report access control and approval workflow must be designed early.

## Pharmacy

- Status: Not started
- Missing requirements:
  - Medicine catalogue, prescriptions, review, dispensing, partial dispensing, substitution controls, batches/lots, expiry, stock receipts/transfers/adjustments/returns, reorder levels, controlled drug records.
- Major risks:
  - Inventory and dispensing must be transaction-safe and audit-logged. Drug interactions should be future integration only.

## Inventory and Procurement

- Status: Not started
- Missing requirements:
  - Suppliers, purchase requests/orders, goods received, batches, stores, issues, transfers, adjustments, damaged/expired stock, reorder alerts, valuation, audit trail.
- Major risks:
  - Must share inventory movement foundations with pharmacy and blood bank where appropriate.

## Blood Bank and Transfusion

- Status: Not started
- Missing requirements:
  - Donors, eligibility screening, donations, blood units, testing, quarantine/release, storage, expiry, cross-match, reservation, issue/return, transfusion administration, reactions, chain of custody.
- Major risks:
  - Safety-critical. Donor eligibility, testing status, quarantine/release, cross-match, transfusion administration, reaction reporting, and haemovigilance require qualified blood-bank and clinical professional approval before implementation.

## Theatre and Procedures

- Status: Not started
- Missing requirements:
  - Schedules, bookings, pre-op checklist, theatre team, consumables, procedure notes, post-op notes, recovery monitoring, charges.
- Major risks:
  - Requires clinical workflow validation and inventory/billing integration.

## Billing and Payments

- Status: Not started
- Missing requirements:
  - Service catalogue, invoices, deposits, receipts, discounts/refunds with approval, payment methods, balances, cashier shifts, collections, reconciliation, reports.
- Major risks:
  - Financial controls, approval workflow, and immutable receipts are required before commercial use.

## Insurance and Corporate Accounts

- Status: Not started
- Missing requirements:
  - HMOs, corporate accounts, plans, eligibility, pre-authorisation, tariffs, claims, batches, rejections/resubmissions, co-payments, receivables.
- Major risks:
  - Tariff and receivables logic can become complex; defer until billing core is stable.

## Reporting and Dashboards

- Status: Not started
- Missing requirements:
  - Operational, clinical, lab TAT, pharmacy stock/sales, blood-bank inventory, revenue, debtors, staff activity, audit/security reports, export controls.
- Major risks:
  - Cannot be built meaningfully until transactional data and audit trails exist.

## Communication and Integrations

- Status: Not started
- Existing:
  - Mail configured to log.
- Missing requirements:
  - Email/SMS notifications, reminders, patient notifications, payment gateway, accounting, lab-device boundaries, PACS/DICOM boundary, API/webhooks, data import/export.
- Major risks:
  - Notifications containing PHI require consent, templates, audit, and suppression controls.

## Audit, Compliance, Backup, and Security Operations

- Status: Not started
- Existing:
  - Laravel logs only.
  - Database queue/cache/session migrations.
- Missing requirements:
  - Audit events, immutable history, backups, restore drills, retention policy, export restrictions, security reports.
- Major risks:
  - Cannot safely pilot clinical or financial workflows without audit and backup foundations.
