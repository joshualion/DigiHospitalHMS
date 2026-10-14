# Phase 3 — authorization and security hardening (2026-10-14)

## Scope and implementation
- Removed the implicit hospital fallback for non-superadmin administrative users in `FoundationController`: staff without a hospital assignment now receive HTTP 403 instead of silently inheriting the first hospital or causing a demo hospital to be created.
- Superadmins without a staff assignment retain the existing primary-hospital administrative behavior for backwards compatibility, but an absent hospital no longer creates an unapproved demo tenant.
- Added active-account conditions to the public-site page, item and media policies.
- Authenticated workspace pages and reports receive private no-store cache headers.
- Extended audit-event secret masking for token, MFA/recovery and credential fields, including nested payloads.
- Added regression tests for unassigned-account denial, cross-hospital patient access, private caching and secret redaction.

## Security controls already present
- Login attempt rate limiting, password confirmation and session regeneration after login/logout.
- CSRF middleware, verified-email routes, role/permission policies on administrative endpoints and a middleware that signs suspended users out.
- Anti-clickjacking, content-type sniffing, referrer and origin-isolation headers, with HSTS on secure production requests.
- Upload MIME/size/dimension checks and hospital-scoped management records.
- A central audit event service with credential masking and hospital-scoped event search.
- Export permission checks on reporting endpoints.

## Remaining acceptance gates — do not equate CI with a penetration test
1. Systematically test cross-hospital and cross-facility access for **every** clinical, medication, transfusion, laboratory, billing and export endpoint using separate user accounts and real test records.
2. Provision hospital-level user and superadmin accounts in a dedicated staging tenant and manually review scopes.
3. Add production MFA for highly privileged accounts, recovery-code policies and session revocation workflows before requiring MFA. Avoid enabling an untested provider integration on existing users.
4. Verify cPanel production `SESSION_SECURE_COOKIE=true`, HTTPS, proxy headers, session lifetime, trusted-host behavior, password reset delivery, backups and logging outside GitHub CI.
5. Perform independent application security testing (OWASP ASVS oriented) including horizontal IDOR, file upload, XSS, CSRF, rate limiting and privilege escalation tests.
6. Confirm sensitive exports, debug configuration, storage permissions, database encryption/key handling and private media are appropriate to the clinical deployment.
7. Perform audit-log retention, access review and restoration drills. Record approvals and clinical safety acceptance.

This document is a source-review checkpoint. It is not a claim of regulatory compliance, production security certification or an exhaustive independent security audit.
