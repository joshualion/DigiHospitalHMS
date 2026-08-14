# Phase 0 Completion Report

Date: 2026-08-14

## Outcome

Phase 0 repository recovery and the revised Inertia/Vue migration are implemented. The application boots, route inspection succeeds, retained tests pass against an isolated test database, and the frontend production build passes.

No hospital-domain migrations or workflows were added.

## Migration Mapping

| Existing interface | Destination |
|---|---|
| Public Blade layout | `resources/js/Layouts/PublicLayout.vue` |
| Admin Blade layout | `resources/js/Layouts/AppLayout.vue` |
| Livewire/Volt login | `resources/js/Pages/Auth/Login.vue` |
| Livewire/Volt registration | `resources/js/Pages/Auth/Register.vue` |
| Password reset pages | `resources/js/Pages/Auth/ForgotPassword.vue`, `ResetPassword.vue` |
| Email verification | `resources/js/Pages/Auth/VerifyEmail.vue` |
| Profile pages | `resources/js/Pages/Profile/Edit.vue` |
| Public marketing pages | `resources/js/Pages/Public/*` |
| Admin dashboard | `resources/js/Pages/Admin/Dashboard.vue` |
| Reusable Blade UI elements | Focused Vue components in `resources/js/Components` |

## Packages

Added:

- `inertiajs/inertia-laravel` `v3.3.1`
- `@inertiajs/vue3` `3.6.1`
- `@inertiajs/vite` `3.6.1`
- `vue` `3.5.41`
- `@vitejs/plugin-vue` `6.0.8`

Removed:

- `livewire/livewire`
- `livewire/volt`
- `laravel/breeze`

Not installed:

- Vue Router
- Pinia
- Sanctum or another API authentication layer
- React, Inertia starter-kit overwrite, or a separate SPA

## Problems Found

- No Git repository existed at the application root or parent path.
- `php artisan route:list` failed on a missing `AuthenticatedSessionController`.
- Tests used stale Breeze/Volt assumptions and the nonexistent `users.name` column.
- Tests were effectively reaching MySQL before the isolated test environment was corrected.
- `User` used `protected $guarded = []`.
- Admin views referenced `Auth::user()->name`.
- Public/admin routes pointed to missing Blade views.
- CMS relationships were missing and pagination was invalid.
- Livewire route cache remained after package removal until Laravel caches were cleared.
- External Alpine loading existed in obsolete Blade layouts.
- PHP `intl` is not loaded in the active CLI PHP configuration.

## Material Changes

- Added controller-backed Laravel session auth routes for registration, login, logout, password reset, email verification, password confirmation, profile update, password update, and account deletion.
- Added Inertia middleware, root Blade template, Vue app bootstrap, Vite Vue/Inertia plugins, shared auth/flash props, and loading indicator hook.
- Added public and authenticated Vue layouts.
- Migrated public pages, auth pages, profile page, dashboard, admin shell, and CMS deferral screens to Vue.
- Added `User::full_name`, explicit `$fillable`, and `MustVerifyEmail`.
- Updated the user factory to use `firstname` and `lastname`.
- Added CMS model relationships and safe read-only/deferred controller behaviour.
- Registered Spatie `role`, `permission`, and `role_or_permission` middleware aliases.
- Added `.env.testing` and `.env.testing.example` with isolated SQLite fallback and documented MySQL preferred setup.
- Updated `.gitignore` for Laravel cache files.
- Removed obsolete Livewire/Volt/Breeze packages and Volt provider registration.

## Tests Added Or Corrected

- Homepage Inertia response.
- Registration with `firstname` and `lastname`.
- Login and failed login.
- POST logout and guest logout protection.
- Password reset request and reset.
- Email verification.
- Password confirmation.
- Profile display and update.
- Password update.
- Account deletion.
- Guest protection of authenticated routes.
- Admin route authorization.
- Non-admin denial from admin routes.
- Route-list command and route-resolution smoke coverage.

## Commands Run And Results

- `composer validate --strict`: passed.
- `php artisan --version`: passed, Laravel `12.28.1`.
- `php artisan route:list`: passed, 36 routes, including Inertia devtools routes in the local environment.
- `php artisan test`: passed, 32 tests and 138 assertions.
- `npm run build`: passed.
- `vendor/bin/pint --test`: passed after formatting.

## Remaining Environment Blockers

- PHP `intl` is still not loaded. Active CLI config: `C:\xampp\php\php.ini`.
- Composer reported 38 security advisories affecting 13 packages during package operations. This is a dependency-security work item, not resolved in Phase 0.
- npm audit reports 10 vulnerabilities. This is not resolved in Phase 0.
- Browserslist data is stale by npm build warning.

## Remaining Deferred Issues

- CMS editing remains deferred and is not implemented.
- Public pages are marketing/static informational pages.
- `access_level` remains present for compatibility but should be deprecated as an authorization source after Spatie roles are fully established.
- No hospital, facility, patient, appointment, billing, pharmacy, laboratory, inventory, blood-bank, admission, tenancy, or audit domain modules exist yet.
- Browser automation was not added; backend/Inertia and build verification were completed.
- SSR is not enabled; consider it later if public-site SEO requires operating a Node SSR process.

## Recommended Phase 1 Starting Point

Start with identity, access, and hospital/facility foundations: staff profile design, facility membership, role/permission matrix, policies, and audit log foundations before any clinical or financial workflow.
