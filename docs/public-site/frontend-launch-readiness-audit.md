# Public Frontend Launch Readiness Audit

Audit date: 2026-08-26  
Scope: public home/landing, about, services, departments, doctors/profile, news/article, contact, appointment request, policies, header/navigation/mobile menu, footer, public theme modes and accents, CMS preview/published behavior, and maintenance-page readiness.  
No application code, routes, controllers, CMS data, CSS, or database structure was changed for this audit.

## Launch Cleanup A Update

Update date: 2026-08-26
Commit scope: launch blockers only.

Verified resolved in Cleanup A:

- Placeholder/demo public seed content is no longer published as live hospital claims. `PublicSiteSeeder` now creates neutral CMS scaffolding and keeps optional services, departments, clinicians, testimonials, news, contact details, and policy/body copy empty until administrators publish approved content.
- Public rendering now uses published CMS payloads and hospital settings as source-of-truth fallbacks. Optional homepage sections hide or show empty/unavailable states instead of inventing services, clinicians, testimonials, news, or contact details.
- Administrators now see launch warnings on the public website index and editor when required content is missing or published content still contains demo markers.
- Public media URLs are normalized more reliably, media usage protection recognizes absolute and storage-relative URLs, and public images render through a reusable `PublicImage` component with alt text, loading/fetch priority controls, stable dimensions, responsive packaged slider variants, and themed missing/broken-image fallbacks.
- The hero slider was verified with zero, one, multiple, and deliberately broken image states. Hero copy remains readable when media is unavailable.
- Oversized packaged slider images were left intact and derivative WebP variants were generated under `public/frontend/images/slider/responsive/` for safe responsive use.
- Public SEO metadata now includes normalized absolute canonical URLs, titles/descriptions from published CMS data with hospital fallbacks, Open Graph type/url/image/alt, Twitter card/title/description/image, favicon, and preview `noindex,nofollow`.
- Appointment request feedback now includes submitting, success, validation-error, and server-error states, prevents duplicate frontend submissions, preserves validation-error values, focuses/scrolls to feedback, and uses confirmation wording that does not promise automatic booking.
- Public empty-list, missing-content, broken-image, and unavailable-page states were added or tightened across the shared public pages.

Verified remaining for Launch Cleanup B:

- The production Vite bundle still emits the existing chunk-size warning (`app-DBZ-3Ipj.js` about 768 KB before gzip). General Vite chunk splitting remains intentionally deferred.
- Full publish-time broken-link validation for every CMS URL is still deferred.
- CMS control for every system fallback string, richer structured data, full page-by-page accent regression, maintenance-page routing, and broader accessibility polish remain deferred.

## 1. Current strengths

- The public site has a modern Inertia/Vue shell with CMS-backed pages, sections, items, navigation, footer content, theme defaults, preview mode, and published-mode separation.
- Header and mobile navigation are responsive, include a skip link, close on mobile navigation, and expose the theme switcher on desktop and mobile.
- The home page has strong launch structure: hero slider, CTA buttons, information band, about block, two-column services accordion, departments, clinicians, testimonials, appointment CTA, news preview, and contact panel.
- Light/dark mode and the configured accent set are implemented through CSS variables and `usePublicTheme`, so the public site has one theme mechanism instead of per-page styling.
- Appointment request avoids clinical-history collection and includes consent, spam honeypot field, duplicate-submit protection through `form.processing`, and Inertia validation binding.
- CMS preview route correctly requires a valid signed URL or authorization and sends `noindex,nofollow` in preview mode.

## 2. Launch-blocking defects

1. Placeholder/demo content is still visible in seeded and fallback public content. **Resolved in Cleanup A for public seed/live fallback paths.**
   - Examples include `Demo Hospital`, `info@example.test`, `Replace placeholder copy`, `Sample clinician profile`, placeholder testimonials, placeholder news, and generic policy/about copy in `database/seeders/PublicSiteSeeder.php`.
   - This must be replaced or blocked from publication before launch.

2. Public media reliability is not launch-ready. **Resolved in Cleanup A for public rendering, hero states, media URL normalization, usage protection, and packaged hero derivatives.**
   - Recent admin media work indicates broken library previews and inability to replace slider images reliably. The public hero depends directly on `activeSlide.image` with no visible fallback state if the selected image is missing.
   - Several hero assets are oversized: `public/frontend/images/slider/1.png` is about 2.85 MB, `3.png` about 2.23 MB, and `222.png` about 3.53 MB.

3. SEO and social metadata are incomplete for launch. **Resolved in Cleanup A for titles, descriptions, canonical URLs, Open Graph, Twitter card metadata, preview robots, favicon/logo/social fallbacks.**
   - `WebsitePage.vue` emits title, description, OG title/description/image, canonical, and preview robots, but there is no visible `og:type`, Twitter card metadata, absolute canonical normalization, per-profile canonical generation, structured data, or default social image fallback.
   - Seeded canonical URL for home is `/`, which is not production-safe without absolute canonical handling.

4. Appointment request success/failure feedback is too thin for public launch. **Resolved in Cleanup A.**
   - The form disables during submission and shows field errors, but no modern success confirmation/toast or clear post-submit next step is visible in the component.
   - Select/date validation messages are not rendered beside every field, only the shared `TextInput` fields and consent show errors visibly.

5. Production asset size needs action before launch. **Deferred to Launch Cleanup B.**
   - Current built public bundle includes `public/build/assets/app-B0kXydYk.js` at about 760 KB before gzip and `app-DZccpBMi.css` at about 84 KB.
   - The existing Vite chunk warning remains relevant; the public/admin bundle should be split or lazily loaded before a public launch.

## 3. Important improvements

- Add a resilient public-image component or image fallback policy for hero, about, clinician, article, and CMS-uploaded images.
- Add CMS-managed controls for every visible fallback string and empty state, or clearly mark non-editable system messages in the admin.
- Improve appointment request UX with success state, failure toast, field-level messages for all inputs, and visible privacy/consent copy that is CMS-manageable.
- Add complete social metadata and canonical generation in the controller/page payload layer.
- Add broken-link validation to the public website admin before publishing navigation, CTAs, info-band links, service CTAs, footer links, and directions URLs.
- Add image optimization requirements to media upload: dimensions, size warnings, conversion/WebP variants, alt text enforcement, and preview before save.
- Add public smoke tests that cover the published page list, preview route authorization, media URLs, theme switching, mobile menu, and appointment validation.

## 4. Optional polish

- Add previous/next hero controls and pause/play state for accessibility.
- Add visible active-slide labels where the image itself does not communicate enough context.
- Add breadcrumb or back links on doctor profile and article pages.
- Add contact map/embed only when CMS has a verified map URL.
- Add author/date/category presentation for news articles.
- Add richer doctor profile fields once approved public staff data is available.

## 5. Hardcoded Content Still Requiring Admin Control

- Public fallback strings in `resources/js/Pages/Public/WebsitePage.vue`, including empty department/doctor/news messages, default appointment CTA copy, contact panel description, and generic page-ready copy.
- Appointment request page text in `resources/js/Pages/Public/AppointmentRequest.vue`, including heading, safety instruction, consent wording, and submit label.
- Footer fallback summary in `resources/js/Layouts/PublicLayout.vue`.
- Legacy public page components under `resources/js/Pages/Public/*.vue` contain static copy and should either be removed from active launch paths or brought under CMS control if reused.
- Seeded content in `database/seeders/PublicSiteSeeder.php` remains heavily demo-oriented and should not be treated as production content.

## 6. Page-by-page findings

### Home / Landing

- Strong section sequence and professional structure.
- Hero content and CTAs are CMS-driven, but hero has no missing-image fallback and depends on large static images.
- The information band is useful and responsive, but all labels, links, and phone/location values must be verified from CMS before launch.
- Two-column services accordion exists and is responsive, but the service entries are placeholder-level and do not yet represent real hospital services.

### About

- Renders through the shared CMS page hero and body region.
- Current seeded body is placeholder copy and is not launchable.
- Needs richer hospital-specific content, governance-approved imagery, and SEO/social image.

### Services

- Uses the two-column accordion component with accessible button/panel structure.
- Current seeded services are generic informational entries and should be replaced with approved service copy.
- Service CTA links should be validated before publishing.

### Departments

- CMS item cards render correctly for published department items.
- Department summaries are placeholder strings generated from internal department records.
- Needs public-specific summaries and optional department profile routes if required.

### Doctors and Doctor Profiles

- Listing and profile routes exist.
- Current profile is explicitly a sample placeholder and is launch-blocking until replaced with approved staff-linked public profiles.
- Profile body is rendered with `v-html`; content must be sanitized upstream or constrained by the CMS editor.

### News and Articles

- Listing and article routes exist.
- Current article is a placeholder launch note and not production news.
- Article page lacks visible publish date/category/share metadata in the current public component.

### Contact

- Contact details are pulled from hospital settings and public shell content.
- Seeded contact values include `info@example.test`, generic Lagos location, and generic hours.
- Directions URL is present in seeded content but not strongly surfaced as a verified external map/action.

### Appointment Request

- Good clinical-safety boundary: it asks for contact/preferences only and tells users not to enter symptoms/diagnoses/history.
- Needs complete visible validation for all fields, success feedback, failure feedback, and clearer consent/privacy text before launch.
- Styling uses the public card surface and should follow mode variables, but it imports shared `PrimaryButton`/`TextInput`, so public theme alignment should be checked after any shared component changes.

### Policies

- Page route exists and renders through the CMS page.
- Current content is placeholder policy copy. Privacy, terms, data handling, appointment consent, and clinical disclaimer content must be approved before launch.

### Header, Navigation, Mobile Menu

- Header is sticky, responsive, and theme-aware.
- Mobile menu closes after navigation and locks body scroll while open.
- Need publishing-time link validation and a launch review for long hospital names/taglines at 320px.

### Footer

- Footer is CMS-shell driven with navigation and contact blocks.
- Default fallback copy still reads like a configurable foundation and is not launch copy.
- Footer badges are CMS content but current seeded badges are product/process-oriented rather than patient-facing.

### Light, Dark, System Modes and Accents

- Theme system supports light, dark, system preference, and accents: calm, healing, alert, blood, seagrass.
- CSS variables are centralized in `resources/css/app.css`.
- Accent screenshots were captured for the home page at 1440px where the sweep completed; full page-by-page accent regression should be added as an automated test later.

### CMS Preview Versus Published Content

- Preview mode uses draft content and adds `noindex,nofollow`.
- Published mode uses published page, section, and item fields.
- Guest preview is intentionally blocked unless the request has a valid signature.

### Maintenance Page

- No active public maintenance page route/view was found in the audited public route set.
- If launch requires a maintenance mode page, add a branded, theme-aware public maintenance view and test it separately from Laravel's default maintenance response.

## 7. Recommended implementation batches

1. Launch blockers: replace/remove demo content, enforce verified public media URLs, add missing-image fallbacks, optimize hero/media assets, and fix appointment success/error feedback.
2. SEO and publishing quality: canonical normalization, OG/Twitter defaults, social image fallback, structured data, publish-time broken-link checks, and preview/published smoke tests.
3. CMS control completion: move remaining fallback copy, appointment text, footer fallback, empty states, and policy/contact details into CMS-managed settings.
4. Performance: split public/admin bundles, lazy-load admin-only code away from public pages, compress and resize public images, and add bundle-size budgets.
5. Accessibility polish: full keyboard check for hero slider/theme menu/mobile nav, visible focus regression, color contrast review for every accent, and reduced-motion handling for all animated sections.

## 8. Exact files likely to change

- `resources/js/Pages/Public/WebsitePage.vue`
- `resources/js/Pages/Public/AppointmentRequest.vue`
- `resources/js/Layouts/PublicLayout.vue`
- `resources/js/Components/Public/InfoBand.vue`
- `resources/js/Components/Public/ServicesAccordion.vue`
- `resources/js/Components/Public/PublicPageHero.vue`
- `resources/js/Components/Public/ThemeSwitcher.vue`
- `resources/js/Composables/usePublicTheme.js`
- `resources/css/app.css`
- `app/Http/Controllers/PublicSiteController.php`
- `app/Http/Controllers/PublicAppointmentRequestController.php`
- `app/Http/Controllers/Admin/PublicWebsiteController.php`
- `app/Models/PublicSiteMedia.php`
- `database/seeders/PublicSiteSeeder.php`
- `routes/web.php` if maintenance-page routing or SEO endpoints are added
- `vite.config.js` if bundle splitting/manual chunks are introduced
- Public media assets under `public/frontend/images/**`
- Public website admin components under `resources/js/Pages/Admin/PublicWebsite/**` and `resources/js/Components/Admin/PublicWebsite/**` for media validation and publish checks
- Public feature/browser tests under `tests/Feature/**` and any Playwright smoke scripts under `scripts/**`

## 9. Screenshot locations

Screenshots are stored in ignored local storage:

- Root: `storage/app/public-site-launch-audit`
- Light mode calm screenshots:
  - `storage/app/public-site-launch-audit/light-calm/320`
  - `storage/app/public-site-launch-audit/light-calm/375`
  - `storage/app/public-site-launch-audit/light-calm/768`
  - `storage/app/public-site-launch-audit/light-calm/1024`
  - `storage/app/public-site-launch-audit/light-calm/1440`
- Dark mode calm screenshots:
  - `storage/app/public-site-launch-audit/dark-calm/320`
  - `storage/app/public-site-launch-audit/dark-calm/375`
  - `storage/app/public-site-launch-audit/dark-calm/768`
  - `storage/app/public-site-launch-audit/dark-calm/1024`
  - `storage/app/public-site-launch-audit/dark-calm/1440`

Captured page set: `home`, `about`, `services`, `departments`, `doctors`, `doctor-profile`, `news`, `article`, `contact`, `appointment-request`, and `policies`.

Screenshot artifact count: 110 files, about 13 MB. Light-mode captures are full-page PNGs. Most dark-mode captures are PNGs; dark 1440 follow-up captures for non-home/about pages are viewport JPEGs because full-page Playwright runs exceeded the command timeout.

## Verification notes

- Local public server responded with HTTP 200 for `/`, `/about`, `/services`, and `/appointment/request`; sampled public route checks succeeded.
- Production asset inspection found `public/build/assets/app-B0kXydYk.js` at about 760 KB and `public/build/assets/app-DZccpBMi.css` at about 84 KB.
- Browser screenshot capture used installed Chrome through Playwright because the bundled Playwright Chromium was not installed.
- Full automated console/network/overflow JSON generation timed out in this Windows environment before writing a result file. Screenshot capture did complete for the requested width/mode matrix, and static/HTTP checks were used for the audit findings above.
