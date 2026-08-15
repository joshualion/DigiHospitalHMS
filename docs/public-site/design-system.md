# Public Site Design System

Phase 1B.1 replaces the Phase 1B presentation layer with a token-driven public design system while retaining the same CMS, publishing, preview, media, policy, audit, and SEO foundation.

## Visual Direction

- Calm, premium healthcare interface with centered hero and major section introductions.
- Detailed body copy remains left-aligned for readability.
- Cards use small radii, intentional borders, and restrained elevation.
- Public pages share page heroes, section headings, buttons, cards, empty states, CTAs, social links, and footer structure.
- No fabricated medical statistics, fake accreditation claims, or operational booking promises are introduced.

## Tokens

Public surfaces are controlled through CSS custom properties in `resources/css/app.css`:

- Page and surfaces: `--public-bg`, `--public-bg-muted`, `--public-surface`, `--public-surface-elevated`.
- Text: `--public-text`, `--public-text-secondary`, `--public-text-muted`.
- Structure: `--public-border`, `--public-input`, `--public-header`, `--public-footer`, `--public-footer-text`.
- Accent: `--public-accent`, `--public-accent-hover`, `--public-accent-active`, `--public-accent-soft`, `--public-accent-foreground`, `--public-focus`.
- States: `--public-success`, `--public-warning`, `--public-danger`, `--public-info`.
- Depth and imagery: `--public-shadow`, `--public-shadow-soft`, `--public-hero-overlay`.

Reusable utility classes include `public-theme`, `public-container`, `public-section`, `public-card`, `public-muted`, `public-border`, `public-kicker`, `public-prose`, `btn-public-primary`, and `btn-public-secondary`.

## Accent Palettes

- Calm Blue: default hospital identity.
- Healing Green: soft clinical wellness accent.
- Warm Gold: accessible gold treatment, never pale yellow text.
- Vital Red: controlled urgent tint. Danger/error tokens remain separate from the red accent.

Each accent has light and dark token values. Components use semantic tokens instead of raw accent color classes.

## Component Patterns

- `PublicLayout.vue`: utility bar, sticky header, mobile navigation, theme control, footer.
- `ThemeSwitcher.vue`: appearance and accent selection.
- `PublicPageHero.vue`: standard page hero.
- `SectionHeading.vue`: centered section labels/headings/intros.
- `InfoBand.vue`: hero-to-about transition panel.
- `ServicesAccordion.vue`: real published marketing services in a two-column accordion.
- `PublicButton.vue`: token-aware primary and secondary calls to action.

The services accordion permits one open service across the whole component. This keeps keyboard state predictable and prevents tall double-column sections from shifting excessively.

## Accessibility

- Skip-to-content link is available on public pages.
- Keyboard focus uses the semantic focus token.
- Accordion buttons expose `aria-expanded` and `aria-controls`.
- Slider and mobile navigation controls have accessible names.
- Reduced-motion users receive no smooth scrolling or long transitions.
- Icons are decorative unless the control requires an explicit label.

## Media

Primary public imagery continues to use project-owned assets in `public/frontend/images`. Below-fold images are lazy-loaded where practical. Uploaded media remains governed by the Phase 1B media validation and licensing policy.
