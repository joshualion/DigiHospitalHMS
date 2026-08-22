# Phase 1B.2 Completion Report

Date: 2026-08-22

## Outcome

Phase 1B.2 enforces the public website publishing boundary. Draft edits for public-visible page, section, and item fields are stored separately from live published fields and do not change the public website until an authorized publish action succeeds.

No patient, appointment, clinical, billing, laboratory, pharmacy, inventory, admission, blood-bank, theatre, or insurance/HMO modules were added.

## Implemented

- Added additive draft/published metadata fields to `public_site_pages`, `public_site_sections`, and `public_site_items`.
- Updated `PublicSitePublisher` so page, section, and item publish operations are transactional, revisioned, and audited.
- Added item unpublish route, controller action, policy-backed authorization, admin control, revision record, and audit event.
- Fixed public doctor and article detail pages to render published item content and media.
- Replaced hardcoded public brand name, tagline, footer badges, and major homepage headings with hospital/CMS-managed values.
- Normalized canonical SEO metadata to `canonical_url`.
- Replaced nonexistent admin `page.version` and `revision.event` references with `published_version` and `action`.
- Added authorized media delete UI with confirmation while preserving server-side usage protection and audit logging.
- Quarantined legacy `/admin/pages` as a preserved archive, leaving `/admin/public-website` as the active public-site management area.

## Tests

Regression coverage was added for:

- Draft edits not altering public output.
- Publish making changes live.
- Unpublish removing public item content.
- Revision restore into draft.
- Unauthorized publish/unpublish rejection.
- Doctor and article detail rendering.
- Canonical metadata propagation.
- Media deletion protection and audited deletion.

## Remaining Gaps

- Many public-site section and item fields are still edited as structured JSON rather than field-specific forms.
- Media usage counts are protected but not automatically recalculated from JSON content references.
- Legacy CMS records are preserved but not migrated into the active `public_site_*` model automatically.
