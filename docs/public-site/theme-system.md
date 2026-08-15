# Public Site Theme System

Phase 1B.1 adds visitor-selectable appearance and accent preferences without adding Pinia, Vue Router, or a separate frontend application.

## Runtime Model

`resources/js/Composables/usePublicTheme.js` manages:

- Appearance preference: `light`, `dark`, or `system`.
- Resolved system appearance through `prefers-color-scheme`.
- Accent preference: `calm`, `healing`, `alert`, `blood`, or `seagrass`.
- Administrator allowed accent options.
- Visitor preference persistence in `localStorage`.
- Invalid stored-value fallback.
- HTML attributes consumed by CSS tokens:
  - `data-public-appearance`
  - `data-public-appearance-preference`
  - `data-public-accent`

The root Blade document applies the stored or system theme before Vue mounts to avoid a visible flash of the wrong theme.

## Administrator Defaults

The Public Website page editor includes a `Public Theme Defaults` draft form. Authorized users with `website.manage_theme` can configure:

- Default appearance.
- Default accent.
- Allowed visitor accents.
- Theme-switcher visibility.

Values are enumerated and validated server-side. Arbitrary CSS, raw colors, scripts, and untrusted style input are not accepted.

These settings are stored in page draft content and become public only through the existing publish workflow. Visitor browser preferences override the administrator default on that browser.

## Auditing And Permissions

Theme updates require `website.edit` plus `website.manage_theme`. The server records `website.theme_updated` audit events with before/after theme metadata. Publishing the draft remains a separate `website.publish` action.

## Contrast Notes

The palettes use the predefined hospital colors supplied for Phase 1B.1 follow-up customization. Alert uses dark text where the amber background requires it. Blood does not replace error colors; danger remains semantically distinct.
