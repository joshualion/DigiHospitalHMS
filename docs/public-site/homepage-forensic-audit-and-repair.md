# Homepage Forensic Audit and Repair

Audit date: 2026-08-31

Scope: public homepage, admin homepage editor, Phase 1A/1B public-site integration, media URLs, canonical doctor/department/service sources, tests, and production rollout notes.

## Root Causes

1. The homepage editor route itself was valid, but the editor was not safe enough for empty or partially configured homepage data. The page editor assumed populated sections and complete media/source props, so Cleanup A-style data removal could leave the Vue editor with no active section or missing nested values to render. The repair adds empty/partial editor states, media diagnostics, source diagnostics, and regression tests for empty and partially configured pages.
2. Page ID `10` exists locally and is the correct homepage: `id=10`, `hospital_id=2`, `slug=home`, `title=Home`, `status=published`, `published_at=2026-08-26 15:00:23`.
3. Uploaded public-site media used `asset(Storage::url(...))`, which produces an absolute URL tied to `APP_URL`. In production this can break when `APP_URL`, HTTPS, host, proxy headers, or subdirectory deployment do not match the public domain. The repair now stores/returns storage-relative URLs from the public disk, e.g. `/storage/public-site/...`.
4. Local browser verification initially rendered a blank page because an untracked stale `public/hot` file made Laravel load Vite dev-server assets from `http://[::1]:5173`. Removing that local artifact allowed the production build to render normally.

## Homepage Data Sources

| Homepage section | Source | Admin control |
| --- | --- | --- |
| Hero/slider | CMS homepage section `hero` | Public website editor: slides, image, alt text, labels, links, order, enable/disable, draft, preview, publish, revisions |
| Information bar | CMS homepage section `info_banner` | Public website editor: items, labels, text, icons, links, order, enable/disable, draft, preview, publish, revisions |
| About | CMS homepage section `about` | Public website editor: heading, copy, image, points, CTA, order, enable/disable, draft, preview, publish, revisions |
| Services | Canonical `billable_services` for cards; CMS section for heading/copy/order/visibility | Billing service catalogue public website fields; public website editor section controls |
| Departments | Canonical `departments` for cards; CMS section for heading/copy/order/visibility | Departments public website fields; public website editor section controls |
| Featured doctors | Canonical `staff_profiles` joined to active users; CMS section for heading/copy/order/visibility | Staff public website fields; public website editor section controls |
| Trust/statistics | CMS homepage section `why_choose_us` | Public website editor |
| Testimonials | CMS public-site testimonial items | Public website editor item controls, publish/unpublish, revisions |
| Calls to action | CMS homepage section `appointment_cta` | Public website editor |
| News | CMS public-site article items | Public website editor item controls, publish/unpublish, revisions |
| Contact information | CMS homepage section plus hospital settings fallback | Public website editor and hospital settings |
| Footer content | CMS page shell content | Public website editor Branding & SEO tab |

## Dynamic Record Rules

Doctors/clinicians:

- Public output uses `staff_profiles` as the canonical source.
- A clinician must be active, belong to the current hospital, have an active user account, be marked `public_is_visible`, and match a clinical qualification signal.
- Homepage output additionally requires `public_is_featured`.
- Public fields are limited to display name, specialty/designation, summary, photo, photo alt text, department/role label, slug, and display order.
- Private staff details such as email, internal notes, work phone, license fields, and facility membership internals are not exposed.

Departments:

- Public output uses canonical `departments`.
- A department must belong to the current hospital, be active, and be marked `public_is_visible`.
- Homepage output additionally requires `public_is_featured`.
- Public fields include public name, public description, icon/image, slug, and display order.

Services:

- Public output uses canonical `billable_services`.
- A service must belong to the current hospital, be active, and be marked `public_is_visible`.
- Homepage output additionally requires `public_is_featured`.
- Public fields include public name, public description, icon/image, department label, slug, and display order.
- Billing codes, prices, tax settings, discounts, cost configuration, and inactive services are not exposed.

## Media Flow

Upload flow:

1. Admin uploads media through `admin.public-website.media.store`.
2. Laravel validates the file and stores it on the `public` disk under `public-site/...`.
3. `PublicSiteMedia` persists the disk path.
4. The media URL resolver returns `/storage/{path}` using `Storage::url($path)`.
5. Inertia sends that relative URL to the Vue editor and public page.
6. Public Vue components render the image and fall back safely when the file is missing.
7. Production must have `public/storage -> storage/app/public` linked.

Operational notes:

- `APP_URL` no longer controls uploaded media URL generation.
- Existing uploaded files are not deleted or overwritten.
- Missing referenced media now creates an admin warning in the homepage editor.
- Packaged default hero media is used only when no valid published slide image is configured or an image fails on the client.
- Zero, one, multiple, and broken hero-image states are covered by Playwright smoke.

## Production Deployment

Use a three-hour maintenance window. Do not deploy automatically from this workstation.

Pre-flight:

```bash
php artisan down --render="errors::503"
cp .env .env.backup.$(date +%Y%m%d%H%M%S)
php artisan about --only=environment
php artisan migrate:status
test -L public/storage || test -e public/storage || php artisan storage:link
```

Deploy:

```bash
git fetch origin
git checkout main
git pull --ff-only origin main
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan route:list --name=admin.public
php artisan up
```

Production smoke:

```bash
curl -I https://testimonyhealthcare.com/
curl -I https://testimonyhealthcare.com/admin/public-website/pages/10
php artisan tinker
```

In Tinker, confirm the live connection and homepage:

```php
config('database.default');
DB::connection()->getDatabaseName();
App\Models\PublicSitePage::find(10)?->only(['id','hospital_id','slug','title','status','published_at']);
```

## Rollback

If the release fails before migrations:

```bash
php artisan down --render="errors::503"
git checkout <previous-good-commit>
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan up
```

If the release fails after migrations:

```bash
php artisan down --render="errors::503"
php artisan migrate:rollback --step=1 --force
git checkout <previous-good-commit>
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan up
```

Before rollback, preserve diagnostics:

```bash
php artisan route:list --name=admin.public > storage/logs/admin-public-routes.rollback.txt
tail -n 200 storage/logs/laravel.log > storage/logs/laravel.rollback-tail.txt
```

## Administrator Checklist

- Confirm homepage page ID and slug in production.
- Fill and publish SEO title and meta description.
- Configure hero slides with uploaded images and alt text.
- Verify `public/storage` exists and uploaded image URLs open over HTTPS.
- Configure information bar phone, hours, appointment, and location links.
- Enable public website fields on featured services in Billing Catalogue.
- Enable public website fields on featured departments.
- Enable public website fields on active clinical staff profiles that may appear publicly.
- Review testimonials and news for approval/consent before publishing.
- Confirm footer summary, badges, copyright, contact details, and theme switcher preference.
- Preview drafts before publishing.
- Check editor media warnings before launch.

## Remaining Launch Blockers

- Production content still needs final administrator review: SEO, hero slides, services, departments, doctors, testimonials, news, contact, and footer.
- The Vite build still emits the known large chunk warning. That belongs to the deferred Cleanup B/performance work and was not changed here.

## Browser Verification

- Public homepage smoke passed for zero, one, multiple, and deliberately broken hero-image states at 375px, 768px, 1024px, and 1440px.
- Authenticated admin editor smoke passed for `/admin/public-website/pages/10` at 375px, 768px, 1024px, and 1440px.
- Browser checks reported no JavaScript console errors, page errors, or unexpected first-party request failures.
