# Local Development Setup

Date: 2026-08-14

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

If `php -m` does not list `intl`, edit that file and enable:

```ini
extension=intl
```

Then restart Apache/PHP processes and open a new terminal. Verify with:

```bash
php -m
```

Do not make unsafe system-wide PHP changes unless you know which PHP binary your web server and CLI are using.

## Install

```bash
composer install
npm install
copy .env.example .env
php artisan key:generate
```

Configure `.env` for the development database. Do not commit `.env`.

## Database

Create the development database, then run:

```bash
php artisan migrate
php artisan db:seed
```

The current seeders create roles, permissions, and preserved CMS marketing content. No hospital-domain migrations exist in Phase 0.

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
