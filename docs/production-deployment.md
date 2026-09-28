# Production Deployment Runbook

This runbook describes the minimum production deployment process for DigiHospitalHMS.

## Server requirements

- PHP 8.2 or newer with the extensions required by Laravel and the configured database driver.
- Composer 2.
- A supported relational database.
- HTTPS with a valid certificate.
- A cron scheduler capable of running Laravel's scheduler every minute.
- A process supervisor when a non-sync queue driver is used.
- Writable `storage` and `bootstrap/cache` directories.

Node.js is not required on the production server when the committed `public/build` assets were produced by CI.

## Environment

Create `.env` from `.env.example` and set installation-specific values. Never commit production secrets.

Minimum production safety settings:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://hospital.example.com
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
INERTIA_ENCRYPT_HISTORY=true
```

Configure the database, mail transport and any optional integrations separately.

For SMS reminders, the installation may expose a compatible HTTPS webhook and configure:

```env
SMS_WEBHOOK_URL=
SMS_WEBHOOK_TOKEN=
SMS_WEBHOOK_SENDER=
```

## Deploy

Use a release/maintenance window for schema-changing releases.

```bash
git pull origin main
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

The repository tracks production Vite assets in `public/build`; do not run npm on a shared production server unless intentionally rebuilding assets there.

## Scheduler

Appointment reminders and other scheduled HMS tasks require Laravel's scheduler:

```cron
* * * * * cd /path/to/DigiHospitalHMS && php artisan schedule:run >> /dev/null 2>&1
```

Only one scheduler should execute for a single installation unless distributed scheduling is intentionally configured.

## Queue

If `QUEUE_CONNECTION` is not `sync`, run a supervised worker and restart it after deployments:

```bash
php artisan queue:restart
```

## Permissions

The web/PHP process must be able to write only where Laravel requires it, especially `storage` and `bootstrap/cache`. Do not make the entire application tree world-writable.

## Backups

Database/file backups are infrastructure responsibilities. The HMS backup monitor records evidence; it does not create backups itself.

Before production release:

1. Confirm an automated database backup exists.
2. Confirm uploaded/private files are included where required.
3. Record the successful backup under **Commercial → Backup & Restore Monitor**.
4. Periodically restore a backup into an isolated environment and record the restore verification date.

## Post-deploy smoke checks

- `/up` returns healthy.
- Public homepage and appointment request render.
- Admin login works over HTTPS.
- Patient/staff searches are hospital-scoped.
- Appointment booking and queue pages load.
- Billing and payments load.
- Clinical, lab, radiology, pharmacy, admissions, blood bank and insurance worklists load for authorized roles.
- Reports render.
- Notifications page shows the configured mail/SMS status.
- Audit logging is being written.

## Rollback

Application-code rollback must be coordinated with database compatibility. Do not blindly roll back migrations on a live clinical database. Prefer restoring the previous application release when its schema remains forward-compatible; otherwise use the tested database restore procedure.
