# Current State Audit

Date: 2026-08-14

## Executive Summary

This repository is a small Laravel application scaffold with authentication, a public marketing website, a minimal admin shell, and an attempted CMS for public pages. It is not yet a functional Hospital Management Solution. No operational hospital modules exist for patients, appointments, encounters, admissions, billing, laboratory, radiology, pharmacy, inventory, blood bank, insurance, reporting, notifications, or multi-hospital management.

The project currently does not run cleanly as an application because route inspection fails on a missing `App\Http\Controllers\Auth\AuthenticatedSessionController` referenced by `routes/auth.php`. Automated tests fail heavily because the Breeze/Livewire tests still expect a `name` field while the `users` migration and registration form use `firstname` and `lastname`. Several routes point to Blade views that do not exist.

## Project Structure

- Application root: `C:\xampp\htdocs\HMS`
- Framework root indicators: `artisan`, `composer.json`, `routes/`, `app/`, `database/`, `resources/`
- First-party application code is limited to:
  - `app/Models/User.php`, `Page.php`, `Section.php`, `Block.php`
  - `app/Http/Controllers/FrontendController.php`
  - `app/Http/Controllers/Cms/PageController.php`, `SectionController.php`, `BlockController.php`
  - `app/Livewire/Forms/LoginForm.php`
  - `app/Livewire/Actions/Logout.php`
  - Laravel providers and Blade components
- No `AGENTS.md` repository instruction file exists.
- This directory is not a Git repository: `git status --short` returned `fatal: not a git repository`.

## Technology Stack

Evidence: `composer.json`, `composer.lock`, `package.json`, `package-lock.json`.

- Laravel: `12.28.1`
- PHP requirement: `^8.2`
- Local PHP CLI: `8.2.12`
- Composer: `2.8.3`
- Node.js: `22.17.0`
- npm: `11.7.0`
- No `engines` field is defined in `package.json`; Node/npm requirements are not explicitly constrained.

Direct Composer packages from lock file:

- `laravel/framework` `12.28.1`
- `laravel/tinker` `2.10.1`
- `livewire/livewire` `3.6.4`
- `livewire/volt` `1.7.2`
- `mallardduck/blade-lucide-icons` `1.23.0`
- `spatie/laravel-permission` `6.21.0`
- Dev: `laravel/breeze` `2.3.8`, `laravel/pint` `1.24.0`, `phpunit/phpunit` `11.5.36`, `laravel/sail` `1.45.0`, `laravel/pail` `1.2.3`, `fakerphp/faker` `1.24.1`, `mockery/mockery` `1.6.12`, `nunomaduro/collision` `8.8.2`

Direct npm packages from installed tree:

- Vite `7.1.5`
- `laravel-vite-plugin` `2.0.1`
- Tailwind CSS `3.4.17`
- `@tailwindcss/forms` `0.5.10`
- `@tailwindcss/typography` `0.5.16`
- `@tailwindcss/aspect-ratio` `0.4.2`
- `@tailwindcss/vite` `4.1.13`
- `axios` `1.11.0`
- `concurrently` `9.2.1`
- `lucide-static` `0.542.0`
- `postcss` `8.5.6`
- `autoprefixer` `10.4.21`

## Frontend Framework and UI

Evidence: `resources/views`, `resources/js/app.js`, `resources/css/app.css`, `tailwind.config.js`, `vite.config.js`, `composer.json`, `package.json`.

- Uses Blade layouts and components.
- Uses Livewire 3 and Volt for auth pages.
- Uses Alpine.js in Blade markup. `resources/views/layouts/app.blade.php` loads Alpine from `//unpkg.com/alpinejs`; frontend views use `x-data`, `x-show`, `x-transition`, and `x-init`.
- Uses Tailwind CSS.
- Uses Blade Lucide icon components.
- No evidence of Vue, React, Inertia.js, Bootstrap, AdminLTE, or jQuery in first-party manifests.
- Dark mode is partially configured via Tailwind `darkMode: 'class'` and a theme switcher component.
- Public pages contain hardcoded sample data and placeholder/external images.

## Database and Environment

Evidence: `.env`, `.env.example`, `config/database.php`, migrations.

- `.env` uses MySQL:
  - `DB_CONNECTION=mysql`
  - `DB_HOST=127.0.0.1`
  - `DB_PORT=3306`
  - `DB_DATABASE=hospital_management_system`
  - `DB_USERNAME=root`
- `.env.example` defaults to SQLite.
- Local MySQL connection is reachable enough for `php artisan migrate:status`.
- All seven existing migrations are marked as run.
- `database/database.sqlite` exists, but the active `.env` uses MySQL.
- Session, cache, and queue are database-backed in `.env`.
- Mail is log-based: `MAIL_MAILER=log`.
- File storage is local: `FILESYSTEM_DISK=local`.

## Authentication

Evidence: `routes/auth.php`, `resources/views/livewire/pages/auth/*.blade.php`, `app/Livewire/Forms/LoginForm.php`, `app/Livewire/Actions/Logout.php`, `config/auth.php`.

- Authentication is Laravel session guard (`web`) with Breeze/Livewire/Volt-style auth screens.
- Login uses `LoginForm` with email/password validation and rate limiting.
- Registration writes `firstname`, `lastname`, `email`, and `password`.
- Logout route is broken: `routes/auth.php` imports and calls `App\Http\Controllers\Auth\AuthenticatedSessionController::destroy`, but that controller file does not exist. A separate invokable `app/Livewire/Actions/Logout.php` exists but is not wired to the route.
- Email verification routes exist.
- Password reset and confirmation routes exist through Volt pages.

## Authorization, Roles, and Permissions

Evidence: `composer.json`, `config/permission.php`, `database/migrations/2025_09_08_191834_create_permission_tables.php`, `database/seeders/RoleSeeder.php`, `PermissionSeeder.php`, `app/Models/User.php`, `bootstrap/app.php`, `routes/web.php`.

- Spatie Laravel Permission is installed and migrated.
- `User` uses `HasRoles`.
- Only `role` middleware alias is registered in `bootstrap/app.php`; permission middleware aliases are not registered.
- Admin routes are protected by `auth` and `role:superadmin|admin`.
- Seeded roles are broad static roles: `superadmin`, `admin`, `doctor`, `nurse`, `pharmacist`, `laboratorist`, `radiologist`, `accountant`, `receptionist`, `patient`.
- Seeded permissions are placeholders: `manage pages`, `manage users`, `view reports`, `book appointment`, `manage appointments`, `dispense drugs`, `conduct tests`, `generate bills`.
- No policies exist.
- No route-level or business-logic permission checks exist for clinical, billing, inventory, tenant, or facility data.
- Spatie teams/multi-tenant permission scoping is disabled.

## Routes

Evidence: `routes/web.php`, `routes/auth.php`.

Public routes:

- `/` -> `frontend.home`
- `/about` -> `frontend.about`
- `/doctor` -> `frontend.doctor` but the view is missing.
- `/appointment` -> `frontend.appointment` but the view is missing.
- `/blog` -> `frontend.blog`
- `/contact` -> `frontend.contact`
- `/policies` -> `frontend.policies` but the view is missing.

Authenticated admin routes:

- `/admin/dashboard` -> `admin.dashboard`
- `/admin/pages` -> `PageController@index`
- `/admin/pages/{id}/edit` -> `PageController@edit`, but `resources/views/admin/pages/edit.blade.php` is missing.
- `/admin/pages/{id}` -> `PageController@update`
- `/admin/users` -> `admin.users`, but the view is missing.
- `/admin/roles` -> `admin.roles`, but the view is missing.

Authenticated app routes:

- `/dashboard` -> `dashboard`
- `/profile` -> `profile`

Auth routes:

- register, login, forgot password, reset password, verify email, confirm password, logout.
- Logout route is broken because its controller is missing.

`php artisan route:list` fails with:

```text
ReflectionException
Class "App\Http\Controllers\Auth\AuthenticatedSessionController" does not exist
```

## Middleware

Evidence: `bootstrap/app.php`.

- Laravel default web middleware stack is used implicitly.
- Custom alias: `role` -> `Spatie\Permission\Middleware\RoleMiddleware`.
- No custom middleware for tenancy, facility scope, audit logging, security headers, MFA, branch selection, or export controls.

## Code Inventory

Existing first-party components:

- Models: `User`, `Page`, `Section`, `Block`
- Controllers: base `Controller`, `FrontendController`, CMS `PageController`, empty CMS `SectionController`, empty CMS `BlockController`, `VerifyEmailController`
- Livewire/Volt: auth pages, `LoginForm`, `Logout` action, empty admin layout component
- Views: public marketing pages, Breeze profile/auth layouts, admin dashboard and page index shell
- Migrations: users, sessions, password reset tokens, cache, jobs, Spatie permissions, pages, sections, blocks
- Seeders: roles, permissions, home page content
- Factory: `UserFactory`
- Tests: default Breeze/feature tests and example tests

Not present:

- Services, repositories, form requests, API resources, jobs, events, notifications, policies, observers, custom middleware, commands, mail classes, domain events, audit log models, hospital modules.

## Existing Database Tables

Evidence: migrations and `migrate:status`.

- `users`
- `password_reset_tokens`
- `sessions`
- `cache`
- `cache_locks`
- `jobs`
- `job_batches`
- `failed_jobs`
- `permissions`
- `roles`
- `model_has_permissions`
- `model_has_roles`
- `role_has_permissions`
- `pages`
- `sections`
- `blocks`

Laravel `db:table` introspection failed because the local PHP build does not load `intl`:

```text
RuntimeException
The "intl" PHP extension is required to use the [format] method.
```

## Current Verification Results

Safe commands run:

- `php -v`: passed, PHP `8.2.12`.
- `composer --version`: passed, Composer `2.8.3`.
- `node -v`: passed, Node `22.17.0`.
- `npm -v`: passed, npm `11.7.0`.
- `php artisan --version`: passed, Laravel `12.28.1`.
- `composer validate --strict`: passed, `composer.json is valid`.
- `php artisan migrate:status`: passed, all existing migrations ran.
- `php artisan route:list`: failed on missing `AuthenticatedSessionController`.
- `php artisan test`: failed, 24 failed and 2 passed.
- `npm run build`: passed. It generated `public/build/manifest.json`, `public/build/assets/app-C0G0cght.js`, and `public/build/assets/app-Odu0CvMu.css`.
- `vendor/bin/pint --test`: failed with 12 files containing style issues.
- `php -m`: confirmed `intl` is not loaded.

## Test Failures

Evidence: `php artisan test`.

- 2 tests passed: unit example and application homepage example.
- 24 tests failed.
- Major causes:
  - Tests/factory use `name`; users table has `firstname` and `lastname`.
  - Registration test sets `$name`; Volt registration component exposes `$firstname` and `$lastname`.
  - Several tests call `assertSeeLivewire`, which is not available as used in this setup.
  - Auth logout route references a missing controller.
- Current tests are mostly inherited scaffold tests and do not validate hospital workflows.

## Security Findings

Evidence: current source files.

- `APP_DEBUG=true` in local `.env`; must be false outside development.
- `User` model uses `protected $guarded = []`, which is risky for mass assignment once sensitive fields and tenant/facility IDs are added.
- No hospital, branch, facility, or tenant isolation exists.
- No audit trail exists.
- No MFA support exists.
- No password policy beyond Laravel defaults.
- No policies or permission checks on business actions.
- No signed or temporary access controls for clinical documents.
- No upload validation workflows exist.
- No immutable clinical history or amendment workflow exists.
- No prevention of silent deletion of clinical records exists because clinical records do not exist yet.
- Public layout loads Alpine from an external CDN-like URL, which is undesirable for healthcare deployments that need deterministic builds, CSP, and offline/local-network resilience.
- Public pages include mojibake text such as `Mon â€“ Fri`, indicating encoding issues.
- Admin views reference `Auth::user()->name`, leaking a schema mismatch that will break dashboard rendering for authenticated users.

## Dead Code and Broken/Inconsistent Implementations

- `FrontendController::home()` expects `Page::with('sections.blocks')`, but `Page`, `Section`, and `Block` models define no relationships.
- `PageSeeder` calls `$page->sections()` and `$services->blocks()`, but no relationships exist on the models.
- `PageController@index()` calls `Page::all()->paginate(10)`, which is invalid because `all()` returns a collection.
- `PageController@edit` references missing `admin.pages.edit`.
- `routes/web.php` imports `BlockController` and `SectionController`, but no routes use them; those controllers are empty.
- Public routes point to missing `doctor`, `appointment`, and `policies` views.
- Admin routes point to missing `users`, `roles`, and page edit views.
- `resources/views/admin/dashboard.blade.php` and `resources/views/layouts/admin.blade.php` use `Auth::user()->name`; `users` table has no `name`.
- `resources/views/admin/pages/index.blade.php` is only placeholder copy.
- `resources/views/frontend/home.blade.php` hardcodes services, doctors, departments, blog posts, and testimonials.

## Runtime Status

The application cannot be considered currently runnable end-to-end.

It partially boots:

- Laravel version command works.
- Migration status works.
- Frontend build works.

It fails key runtime checks:

- Route listing fails on missing auth controller.
- Several routes point to missing views.
- Tests fail.
- Auth/admin dashboard have schema mismatches.
- CMS model relationships are missing.

## Existing Functional Completeness

No hospital operations module is complete. Existing work is best described as:

- Authentication: partially implemented, currently broken around logout/tests/schema consistency.
- Roles/permissions: backend skeleton only, not operationally useful for a hospital.
- Public marketing website: interface only/static content.
- Admin dashboard: placeholder.
- CMS/page editor: broken partial backend and placeholder UI.
- Hospital Management Solution functionality: not started.
