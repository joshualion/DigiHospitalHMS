# Public Website Management

The administration area is available at `/admin/public-website` for users with `website.view`. Page editors can manage drafts, structured page settings, sections, content items, SEO, theme defaults, and uploaded media. Publishing controls require `website.publish` or `website.unpublish`.

Use the overview to inspect page status, media, and revision counts. Open a page to manage branding, header/navigation, hero slides, information banner, about, services, departments, clinicians, trust items, testimonials, appointment/contact CTA, news/articles, contact/location, footer, SEO, preview, publish, unpublish, and revision restore.

The editor uses form fields, repeaters, media pickers, toggles, and ordering controls. Draft edits do not affect the live website until an authorized publisher publishes the page or item. Modified, published, unpublished, and disabled states are shown in the editor.

Media uploads require title and alternative text. Do not upload patient-identifiable material, unlicensed assets, arbitrary SVG, or screenshots containing credentials/session material. Media usage is derived from draft and published public-site references. Referenced media cannot be deleted until the reference is removed from draft and published content.

Superadministrators can open a read-only diagnostics view for the underlying draft payload. Normal administrators should not need to edit JSON.

The legacy `/admin/pages` CMS remains available only as a quarantined archive. Manage the active public website from `/admin/public-website`.

## Theme Defaults

The page editor includes `Public Theme Defaults` for the homepage/public shell. Users need `website.manage_theme` in addition to normal website edit access.

Supported values are controlled enumerations:

- Appearance: light, dark, or system.
- Accent: Calm, Healing, Alert, Blood, or Seagrass.
- Allowed visitor accents: one or more of the approved accents.
- Theme switcher visibility: shown or hidden.

Theme settings are saved to draft content. They do not affect the live website until the page is published. Visitor preferences stored in the browser override the published default on that browser.

Theme changes are audited as `website.theme_updated`. Do not paste CSS, HTML, tracking scripts, or arbitrary color values into public-site content fields.
