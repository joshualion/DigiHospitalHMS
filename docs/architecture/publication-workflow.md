# Publication Workflow

Editors save drafts. Draft saves do not alter `published_content` and do not change the live website. Public visitors only query records with published status, enabled visibility, and published timestamps.

Preview uses an authenticated or temporary signed preview route and sets noindex metadata. Publishing is transactional through `PublicSitePublisher`, records a revision snapshot, and writes audit events. Unpublish moves the page or item out of the public query path while preserving published content history.

Rollback restores a previous revision into draft so an authorized publisher can review and publish it deliberately.