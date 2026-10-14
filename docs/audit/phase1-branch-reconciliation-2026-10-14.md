# Phase 1 — Branch reconciliation baseline (2026-10-14)

## Scope and release boundary
Baseline main: `c42a96b41c16dc01c33154acdc96c98fee96c179`. The main CI run `38549490136` passed. No unverified historical branch has been blindly merged. This inventory is a preservation and reconciliation checkpoint, **not a certificate that all historical branch changes are in main**.

## Current repository state
- Main plus 26 historical/non-main branches at inventory time.
- Open PR #11 originates from `phase-foundation-clinician-admin` and must be resolved or explicitly closed before pruning.
- Recent About/News PR #27 was squash-merged into main. The historical feature head is not an ancestor because of squash-merge, which makes ahead counts misleading.
- Numerous older branches report both ahead and behind `main`; direct merging would potentially reintroduce superseded code or old frontend bundles.
- `phase11c-diagnostics-completion` contains an additional 304-line feature test, dedicated laboratory and radiology catalogue editing/removal endpoints and UI changes. These require focused reconciliation/testing.

## Branch inventory and treatment
### Recently incorporated by squash merge
`feature/about-news-editorial-20261013`: About/News changes already on main by PR #27. Treat as superseded after verifying equivalence.

### Public website historical work
`fix/public-pages-complete-cleanup`, `fix/public-services-accordion`, `fix/public-services-page`, `fix/public-site-polish`: review current Vue behavior against historical diffs; **do not** restore old compiled bundles.

### Staff, appointments, facilities
`fix/facility-management-cleanup`, `fix-demo-staff-purge`, `fix-facility-crud-reassign`, `fix-staff-doctor-appointments`, `fix-staff-photo-upload`, `phase-foundation-clinician-admin`, `phase-foundation-clinician-admin-v2`: compare authorization, profile upload, scheduling, facility reassignment and deletion behavior with current tests. Resolve PR #11 before deletion.

### Database/migration hotfixes
`hotfix-mysql-constraint-names`, `hotfix-mysql-index-identifiers`, `hotfix-mysql-index-lengths`: compare final migrations against deployed MySQL migrations and test fresh installations before adopting or discarding changes; never rewrite a migration already applied to a live database without a compatibility plan.

### Earlier module phases
`phase8b-insurance-claims`, `phase9a-reporting`, `phase9b-notifications`, `phase9c-commercial-integrations`, `phase10-hardening-release`, `phase11a-admissions-completion`, `phase11b-inpatient-emar-completion`, `phase-7c-transfusion-monitoring`, `phase-8a-payers-coverage`, `phase-8b-claims-receivables`: historical branches diverged from main; reconcile behavioral coverage rather than re-merge entire branches. The claims-receivables prototype uses an alternate model family and must not be merged automatically.

### Unincorporated candidate
`phase11c-diagnostics-completion`: focused candidate for a fresh branch from main. Port controller, routes, UI and tests selectively, run PHP tests, dependency security audit, production Vite build, and regression checks on relevant diagnostic workflows before merging.

## Pruning gate
Only delete branch heads after:
1. Determine whether unique behavior is already present in main, superseded, or still required.
2. Move required work to a new branch from current main and run relevant tests.
3. Merge and verify CI on main.
4. Preserve audit evidence (PR or comparison notes) and close stale open PRs.
5. Delete **branch references**, not git commit history or tags; keep `main` alone.

## Audit limitations
The GitHub connector available in this session permits file, PR and commit operations but has no branch deletion action. Network-restricted local Git cannot delete remote references either. This must be completed by an authorized GitHub operation before asserting pruning done.

## Follow-up acceptance
- Confirm no required unique work remains on a historical branch.
- Verify branch API lists exactly `main`.
- Record final main SHA and passing CI.
