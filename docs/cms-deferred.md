# CMS Deferred Note

Date: 2026-08-14

The CMS is not part of the Hospital Management Solution MVP and is deferred after Phase 0.

What remains preserved:

- CMS tables and migrations: `pages`, `sections`, `blocks`.
- CMS models: `Page`, `Section`, `Block`.
- CMS controller namespace and source files.
- Existing seeded marketing content.

Phase 0 safety changes:

- Added model relationships needed for safe reads and seeding.
- Replaced invalid `Page::all()->paginate(10)` with query pagination.
- Converted CMS admin routes to boot-safe Inertia pages.
- Disabled CMS editing behaviour by returning a Phase 0 deferred status instead of processing unvalidated nested writes.
- Removed broken CMS editor links from the active navigation except a protected read-only/deferred pages view.

Known deferred CMS work:

- No full editor UI.
- No publish workflow.
- No validation/form request layer.
- No content audit trail.
- Empty section/block controllers remain unused.
- Public marketing pages are currently hardcoded Inertia pages, not CMS-rendered pages.
