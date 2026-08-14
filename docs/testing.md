# Testing Setup

Date: 2026-08-14

## Test Database Strategy

Preferred long-term strategy: a dedicated MySQL test database, separate from development and production data.

Example `.env.testing` values for MySQL:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hms_testing
DB_USERNAME=
DB_PASSWORD=
```

Phase 0 uses an isolated SQLite in-memory fallback in `.env.testing` because no dedicated MySQL test credentials were available and the current migrations are compatible with SQLite:

```env
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
```

Do not point tests at `hospital_management_system` or any other development database.

## Commands

```bash
php artisan config:clear
php artisan test
vendor/bin/pint --test
```

Frontend verification:

```bash
npm run build
```

Repository verification:

```bash
composer validate --strict
php artisan --version
php artisan route:list
```
