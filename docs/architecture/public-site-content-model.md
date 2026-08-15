# Public Site Content Model

Phase 1B introduces additive `public_site_*` tables and preserves the older `pages`, `sections`, and `blocks` records. The new tables are the public-site source of truth because the old CMS lacks hospital scoping, draft/live separation, revision snapshots, publishing actors, media controls, and granular policies.

Core records:
- `PublicSitePage`: hospital-scoped public page with draft content, published content, SEO metadata, status, published actor, and version.
- `PublicSiteSection`: ordered page section with typed JSON schema, enable/disable flag, draft content, published content, and version.
- `PublicSiteItem`: reusable public entries for services, department presentation, clinicians, testimonials, and news.
- `PublicSiteMedia`: secured image metadata and upload ownership.
- `PublicSiteRevision`: restorable publication snapshots.

Operational records are referenced where appropriate. Department presentation can point to Phase 1A departments. Clinician presentation can point to staff profiles without duplicating authentication identities or exposing private fields.