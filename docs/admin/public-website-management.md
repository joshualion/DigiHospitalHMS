# Public Website Management

The administration area is available at `/admin/public-website` for users with `website.view`. Page editors can manage drafts, sections, content items, SEO JSON, and uploaded media. Publishing controls require `website.publish` or `website.unpublish`.

Use the overview to inspect page status, media, and revision counts. Open a page to manage page draft JSON, individual homepage sections, content items, preview, publish, unpublish, and revision restore.

Media uploads require title and alternative text. Do not upload patient-identifiable material, unlicensed assets, arbitrary SVG, or screenshots containing credentials/session material.

## Theme Defaults

The page editor includes `Public Theme Defaults` for the homepage/public shell. Users need `website.manage_theme` in addition to normal website edit access.

Supported values are controlled enumerations:

- Appearance: light, dark, or system.
- Accent: Calm, Healing, Alert, Blood, or Seagrass.
- Allowed visitor accents: one or more of the approved accents.
- Theme switcher visibility: shown or hidden.

Theme settings are saved to draft content. They do not affect the live website until the page is published. Visitor preferences stored in the browser override the published default on that browser.

Theme changes are audited as `website.theme_updated`. Do not paste CSS, HTML, tracking scripts, or arbitrary color values into public-site content fields.
