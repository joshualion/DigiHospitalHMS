# Phase 1B Design Reference Audit

## Recovered Evidence
- Former Blade public templates remain in `resources/views/frontend/*.blade.php`.
- Former public assets remain under `public/frontend/images`, including hero slider images and a doctor placeholder.
- `database/seeders/PageSeeder.php` contains old professional landing-page copy such as “Your Health, Our Priority”, “Expert Doctors”, “Advanced Facilities”, and “Caring for You, Every Step of the Way”.
- Git history shows these public templates and assets were introduced in the baseline commit.

## Reconstructed Direction
The Phase 1B public website reuses the recovered hospital-oriented structure: utility contact strip, sticky header, large image hero, information banner, about, services, doctors, updates, contact, and footer. It does not copy old JavaScript or treat legacy Blade as the active implementation.

## Current Source of Truth
The active public website is Laravel/Inertia/Vue backed by `public_site_*` tables. Legacy CMS tables are preserved but not used as the Phase 1B publishing source of truth.