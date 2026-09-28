# Phase 10 Release Checklist

A release is considered technically ready only after the applicable checks below pass.

## Automated gate

- [ ] Composer dependencies install successfully.
- [ ] Laravel routes compile.
- [ ] Full PHPUnit feature/unit suite passes.
- [ ] Composer security audit has no blocking advisories.
- [ ] Frontend dependencies install with `npm ci`.
- [ ] Production Vite build succeeds.

## Security

- [ ] `APP_ENV=production`.
- [ ] `APP_DEBUG=false`.
- [ ] HTTPS is enforced by infrastructure.
- [ ] `SESSION_SECURE_COOKIE=true`.
- [ ] `SESSION_ENCRYPT=true`.
- [ ] Application key is unique to the installation and stored securely.
- [ ] Database credentials are not committed.
- [ ] Optional provider tokens are not stored in browser-editable integration metadata.
- [ ] Superadministrator accounts are reviewed.
- [ ] Role/permission assignments are reviewed for the installation.
- [ ] Sensitive patient fields remain encrypted at rest where implemented.
- [ ] Audit-event retention and access are reviewed.

## Operations

- [ ] Primary hospital/facility records reflect the real installation.
- [ ] Development/test facilities and sample content are removed.
- [ ] Number sequences are configured.
- [ ] Mail delivery is configured and tested.
- [ ] Laravel scheduler cron is configured.
- [ ] Queue worker is supervised when an asynchronous queue driver is used.
- [ ] Backup automation exists outside the HMS.
- [ ] A recent backup is recorded.
- [ ] Restore procedure has been tested in an isolated environment.
- [ ] Log rotation and disk-space monitoring are configured.

## Clinical acceptance

Automated software tests do not replace hospital acceptance.

- [ ] Hospital management validates facility/department/staff setup.
- [ ] Authorized clinical users validate patient, encounter, medication and inpatient workflows.
- [ ] Laboratory/radiology/pharmacy/blood-bank staff validate their workflows.
- [ ] Billing/accounts validate prices, payments, HMO/insurance and reports.
- [ ] Privacy/data-protection requirements for the deployment jurisdiction are reviewed by the responsible organization.
- [ ] Disaster/recovery and downtime procedures are approved.

## Public website

- [ ] Hospital identity/contact details are correct.
- [ ] About/services/departments/doctors/news content is approved.
- [ ] Placeholder content is removed.
- [ ] Appointment request works.
- [ ] Public clinicians are intentionally marked visible.
- [ ] SEO/social metadata is reviewed.

## Release record

Record the deployed Git commit, migration status, deployment date, operator, backup reference and smoke-test result for each production release.
