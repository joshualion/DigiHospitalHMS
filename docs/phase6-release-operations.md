# Phase 6 — release, upgrade and recovery operations

This procedure is for an existing **DigiHospitalHMS** Laravel installation on a supported PHP host. It complements [the production runbook](production-deployment.md). It is not an unattended installer and never automatically backs up clinical data.

## Deployment prerequisites

- Confirm a clean Git checkout on `main`, with production `.env` **outside Git** and a verified commit SHA.
- Back up and independently verify both the **database** and **private patient attachments / media**. Record backup location, timestamp, database version, file backup scope, checksum and operator in a nonempty local evidence file **outside the Git checkout**. The deployment tool checks file presence only; a human must verify the contents and run restoration drills.
- Verify adequate disk space, PHP 8.2+, required extensions, HTTPS, application key, writable `storage` and `bootstrap/cache`, the tracked Vite build, production cookie security, and database connection.
- If database schema updates are not forward/backward compatible, schedule downtime and rehearse rollback on staging.
- Ensure cron, queue worker restart rights, mail and host-specific permissions are configured.

## Read-only checks

```bash
php artisan hms:preflight --production
php artisan hms:preflight --production --json
php artisan hms:version --json
bash scripts/deploy-release.sh --check
```

Preflight emits a failed exit code if any required check fails. It does not test whether an external backup can be restored, nor does it certify clinical operations.

## Version identity

Set `HMS_RELEASE_VERSION` to the release label and `HMS_RELEASE_REVISION` to the exact merged Git commit SHA in each installation's `.env`. Run `php artisan config:cache` after updating the values. If omitted, the version command prints `unversioned` and `unknown` deliberately rather than guessing.

## Guarded upgrade

Review GitHub CI for the target commit and perform staging rehearsal first. From the installation directory:

```bash
git fetch origin main
git rev-parse origin/main
bash scripts/deploy-release.sh --apply \
  --backup-evidence /secure/ops/backup-confirmation.txt \
  --revision FULL_40_CHARACTER_REVIEWED_MAIN_SHA
```

The tool requires a clean working tree, verifies the exact target SHA and fast-forward ancestry, checks prerequisites, and asks for explicit `DEPLOY` and `OPEN` confirmations. It runs maintenance mode, fast-forward code update, locked Composer install, forward migrations, cache rebuild and queue restart. **Any failure after maintenance mode begins leaves the application down.** If a check fails, preserve logs and involve the operator. This is intentional to prevent serving partially upgraded clinical workflows.

The tool never runs `migrate:rollback`, deletes patient data, drops tables, changes `.env`, creates backups or silently reopens service.

## Post-upgrade acceptance

Check `/up`, public website, authenticated hospital admin, authorized registration/search, clinical worklist, diagnostics, pharmacy, admissions, billing, reports, media downloads, schedule/queue health, private-file storage, audit logs and job delivery. Reconcile sample counts and confirm hospital isolation. If `OPEN` was entered before all smoke checks, schedule a follow-up review; the script cannot verify web-browser flows automatically.

Update `HMS_RELEASE_REVISION` to the **deployed** Git SHA and optionally `HMS_RELEASE_VERSION`, then refresh configuration cache.

## Failure and rollback

1. **Keep maintenance enabled.** Capture error logs, failed Artisan output, `git rev-parse HEAD`, DB migration state and backup identity.
2. For code-only failures and **schema-compatible** releases, deploy a separately approved fix or revert to an explicitly verified older build.
3. If schema migration damaged compatibility, **do not automatically roll migrations back**. In an isolated environment, verify database and uploaded-file restore together. Have operations and the clinical owner approve restoration and acceptable loss of changes after backup.
4. For emergency recovery, restore the matched database, attachments and application revision as a single coordinated operation; validate patient counts, sample records and credentials before reopening.
5. Record an incident report, revision, release version, backup evidence and clinical sign-off.

## Version scope / outstanding automation

This phase establishes readable release identity, read-only server preflight and a controlled upgrade gate. It **does not** implement a self-service web installer, remote paid updates, automatic backup/restore, full rollback orchestration, staging clinical acceptance or clinical compliance certification. Those need separate implementation and infrastructure authorization.
