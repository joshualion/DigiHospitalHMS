# Local Development Setup

Date: 2026-08-15

## Requirements

- PHP: `^8.2`; verified locally with PHP `8.2.12`.
- Composer: Composer 2.x; verified locally with Composer `2.8.3`.
- Required PHP extensions: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `intl`, `json`, `mbstring`, `openssl`, `pdo`, `pdo_mysql`, `pdo_sqlite`, `tokenizer`, `xml`, `xmlreader`, `xmlwriter`, `zip`.
- Node.js and npm: verified with Node `22.17.0` and npm `11.7.0`.
- Database: MySQL is preferred for development parity. SQLite in-memory is currently used only for isolated Phase 0 tests.

## PHP `intl` on Windows/XAMPP

The active CLI configuration is:

```text
C:\xampp\php\php.ini
```

Phase 1A.1 verified that CLI PHP loads `intl` after enabling it in the active XAMPP ini. Verify with:

```bash
php --ini
php -m
php --ri intl
```

If `php -m` does not list `intl`, edit only the active XAMPP `php.ini` and enable:

```ini
extension=intl
```

Then restart Apache for the web SAPI and open a new terminal for CLI verification. Do not alter unrelated PHP settings.

## Install

```bash
composer install
npm install
copy .env.example .env
php artisan key:generate
```

Configure `.env` for the development database. Do not commit `.env`.

## Database

The local Phase 1A database is expected to use `APP_ENV=local` and the configured MySQL database name from `.env`. Do not print or commit database credentials.

Before applying pending local migrations to a database with material data, create a recoverable backup. For XAMPP MySQL, use the local `mysqldump.exe` with credentials read from `.env` and store the backup under ignored local storage such as `storage/app/backups/`.

Run migrations and inspected seeders separately:

```bash
php artisan migrate:status
php artisan migrate
php artisan db:seed
```

Seeders must remain repeatable. Phase 1A.1 verified repeated `php artisan db:seed` does not duplicate roles, permissions, the primary hospital, the primary facility, public pages, sections, blocks, or numbering sequences.

## First Administrator

Use the local bootstrap command after migrations and seeders:

```bash
php artisan foundation:bootstrap-admin --email=admin@example.test --firstname=Local --lastname=Admin
```

The command prompts for a password when one is not supplied, refuses to run in production without `--force-production` and confirmation, refuses duplicate bootstrap when an active superadministrator already exists, creates or uses the selected user deliberately, assigns the seeded hospital and primary facility, assigns the `superadmin` role, and records an audit event.

## Browser Smoke

```bash
php artisan serve --host=127.0.0.1 --port=8000
node scripts/phase1a-smoke.mjs
```

Set these environment variables before running the smoke script:

- `PHASE1A_ADMIN_EMAIL`
- `PHASE1A_ADMIN_PASSWORD`
- `PHASE1A_NON_ADMIN_EMAIL`
- `PHASE1A_NON_ADMIN_PASSWORD`
- `PHASE1A_BASE_URL` when not using `http://127.0.0.1:8000`
- `CHROME_PATH` when Chrome is not installed at the default Windows path

Do not commit local smoke credentials or screenshots.

## Queues And Scheduler

The default local `.env.example` uses database-backed queue/session/cache settings. For local development:

```bash
php artisan queue:listen
php artisan schedule:work
```

Run these only when working on queued or scheduled behaviour.

## Frontend

The active frontend is Inertia.js with Vue 3, Tailwind CSS, and Vite.

```bash
npm run dev
npm run build
```

Internal application links should use Inertia navigation through Vue components. Laravel remains the server-side route authority; Vue Router is not installed.
