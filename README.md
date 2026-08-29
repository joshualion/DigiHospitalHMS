# DigiHospitalHMS

DigiHospitalHMS is a Laravel-based hospital management system with a launch-ready public website and an actively expanding hospital operations backend.

The application is built as a modular Laravel monolith using Inertia, Vue 3, Tailwind CSS, Vite, Spatie Permission, PHPUnit, and Playwright. It is no longer a generic Laravel starter; it contains hospital-domain models, workflows, authorization, audit trails, admin screens, public content management, and deployment support.

## Status

The public website, public content management workflow, admin shell, and branded maintenance experience are launch-ready.

Core HMS development is ongoing. The current backend includes tested foundations and operational workflows through patient identity, appointments, clinical encounters, billing, payments, laboratory, radiology, inventory, pharmacy, procurement, admissions, inpatient charting, eMAR, blood bank, and patient blood requests. Additional production hardening, integrations, reporting, insurance/HMO flows, notifications, and deployment-specific configuration should be completed per installation.

## Key Features

- Public hospital website with responsive Vue/Inertia pages, managed sections, media, theme accents, mobile navigation, appointment request intake, and SEO-friendly published pages.
- Draft, preview, publish, unpublish, revision restore, authorization, and audit workflow for public website content.
- Hospital administration foundations for hospitals, facilities, departments, staff profiles, role/permission access control, settings, numbering sequences, and audit events.
- Patient registration and identity management with hospital numbers, duplicate warnings, protected contact lookup, allergies, alerts, activity history, and scoped access.
- Appointment booking, public request review, clinician availability, queues, walk-ins, check-in, priority handling, and visit transitions.
- Clinical encounter workflow with vitals, diagnosis, lifecycle controls, signed-record immutability, amendments, and audit history.
- Billing catalogue, service pricing, invoices, manual authorized lines, payment allocation, cashier shifts, reversals, refunds, and reconciliation foundations.
- Laboratory and radiology workflows with requests, catalogues, specimens/studies, reports, verification/approval, amendments, billing integration, critical communication, and private attachment handling.
- Inventory, stock ledger, procurement, goods receipt, prescribing, pharmacist review, dispensing, returns, and FEFO/stock controls.
- Admissions, bed management, inpatient clinical charting, nursing documentation, discharge summaries, eMAR scheduling, administration records, and medication audit trails.
- Blood bank foundations for donors, donations, components, screening, storage, transfers, reservations, compatibility testing, patient blood requests, emergency release, issue, return, and reversal.
- Branded Laravel maintenance page with countdown, contact actions, emergency messaging, and native Laravel maintenance-mode support.

## Tech Stack

- PHP 8.2+
- Laravel 12
- Inertia Laravel
- Vue 3
- Tailwind CSS 3
- Vite
- MySQL or SQLite for local development
- Spatie Laravel Permission
- PHPUnit
- Playwright
- Laravel Pint

## Project Structure

- `app/` - application logic, controllers, models, policies, services, middleware, and support classes.
- `config/` - Laravel and application configuration, including public maintenance-page settings.
- `database/` - migrations, factories, seeders, and domain schema evolution.
- `docs/` - architecture notes, module guides, roadmap, audits, setup, testing, and phase completion reports.
- `resources/js/` - Vue pages, layouts, components, and composables.
- `resources/views/` - Blade views, including the custom `503` maintenance page.
- `routes/` - application routes.
- `scripts/` - Playwright and workflow smoke scripts.
- `tests/` - PHPUnit feature and unit tests.

## Local Setup

Requirements:

- PHP 8.2+
- Composer 2.x
- Node.js and npm
- MySQL for normal development, or SQLite for quick local setup

Install dependencies and configure the app:

```bash
composer install
npm install
copy .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
```

Run the application locally:

```bash
php artisan serve
npm run dev
```

Or run the combined Laravel development process defined in Composer:

```bash
composer run dev
```

Build frontend assets:

```bash
npm run build
```

Run the test suite:

```bash
php artisan test
```

Useful setup and verification references:

- [Local development](docs/local-development.md)
- [Testing](docs/testing.md)
- [Hospital management roadmap](docs/roadmap/hospital-management-roadmap.md)
- [Public website management](docs/admin/public-website-management.md)
- [Frontend launch readiness audit](docs/public-site/frontend-launch-readiness-audit.md)

## Maintenance Mode

Enable Laravel maintenance mode with the custom branded page:

```bash
php artisan down --render="errors::503"
```

Enable maintenance mode with a bypass secret:

```bash
php artisan down --render="errors::503" --secret="preview-secret"
```

Bring the application back online:

```bash
php artisan up
```

Maintenance-page values are configured through `.env` / `.env.example`, including:

- `MAINTENANCE_BRAND_NAME`
- `MAINTENANCE_BRAND_TAGLINE`
- `MAINTENANCE_LAUNCH_AT`
- `MAINTENANCE_TIMEZONE`
- `MAINTENANCE_PHONE`
- `MAINTENANCE_WHATSAPP`
- `MAINTENANCE_EMAIL`
- `MAINTENANCE_DIRECTIONS_URL`

## Public Branding

The default public branding currently uses:

- Brand name: `Testimony`
- Tagline: `Healthcare & Surgeries`

This branding is implemented in the public frontend and maintenance page so the website can be deployed before a future logo and brand-management workflow is added.

## Development Direction

DigiHospitalHMS is being developed in controlled phases with an emphasis on hospital scoping, authorization, auditability, clinical safety boundaries, and test coverage.

The public website is ready for launch. Backend HMS development continues toward a broader production-ready hospital platform with integrations, reporting, operational refinements, and installation-specific deployment work.

## License

This project is released as open source under the MIT License. See the `license` field in [composer.json](composer.json) for the current package metadata.

## Author

**Joshua Ekpe** - Senior Laravel/PHP Backend & Full-Stack Engineer, lead developer at Govware Solutions Limited.

Experienced in Laravel, Vue.js, MySQL, REST APIs, SaaS/business systems, application architecture, security, deployment, and technical leadership. Based in Nigeria and open to remote and international software opportunities.

- GitHub: [@joshualion](https://github.com/joshualion)
- Repository: [DigiHospitalHMS](https://github.com/joshualion/DigiHospitalHMS)
- Email: [Email the Author](mailto:joshuaekpe87@gmail.com)