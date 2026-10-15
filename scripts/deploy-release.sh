#!/usr/bin/env bash
# DigiHospitalHMS guarded release deployment. Run from the application repository root.
set -Eeuo pipefail

usage() {
  echo "Usage: bash scripts/deploy-release.sh --apply --backup-evidence /absolute/path/to/verified-backup.txt --revision COMMIT_SHA" >&2
  echo "Preflight-only: bash scripts/deploy-release.sh --check" >&2
}

apply=0; check=0; evidence=""; revision=""
while (($#)); do
  case "$1" in
    --apply) apply=1; shift ;;
    --check) check=1; shift ;;
    --backup-evidence) evidence="${2:-}"; shift 2 ;;
    --revision) revision="${2:-}"; shift 2 ;;
    *) usage; exit 2 ;;
  esac
done

cd "$(dirname "$0")/.."
test -f artisan && test -d .git || { echo "Not an HMS git checkout" >&2; exit 1; }

if ((check)); then
  ((apply == 0)) || { usage; exit 2; }
  php artisan hms:preflight --production
  php artisan hms:version
  exit 0
fi

((apply)) || { usage; exit 2; }
[[ "$revision" =~ ^[0-9a-fA-F]{40}$ ]] || { echo "A complete 40-character target revision is required" >&2; exit 2; }
[[ -n "$evidence" && -f "$evidence" && -s "$evidence" ]] || {
  echo "A nonempty operator-reviewed backup evidence file is required. This does not create or independently validate backups." >&2
  exit 1
}
[[ -z "$(git status --porcelain)" ]] || { echo "Working tree is dirty; release refused" >&2; exit 1; }
current="$(git rev-parse HEAD)"
git fetch --quiet origin main
target="$(git rev-parse origin/main)"
[[ "$target" == "$revision" ]] || { echo "Target SHA must equal the reviewed origin/main revision" >&2; exit 1; }
git merge-base --is-ancestor "$current" "$target" || { echo "Deployment would not be fast-forward; refused" >&2; exit 1; }

echo "Current: $current"
echo "Target:  $target"
echo "Backup evidence: $evidence"
echo "Maintenance will REMAIN ENABLED if any deployment step fails."
php artisan hms:preflight --production
read -r -p "Type DEPLOY to proceed after validating DB AND private-file backups: " confirmation
[[ "$confirmation" == "DEPLOY" ]] || { echo "Cancelled"; exit 1; }

php artisan down --render="errors::503"
# Deliberately no trap to bring the site online on errors.
git merge --ff-only "$target"
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
php artisan hms:preflight --production

echo "Code and database checks passed. Perform live smoke checks before reopening."
read -r -p "Type OPEN after smoke-check approval (anything else leaves maintenance enabled): " reopening
[[ "$reopening" == "OPEN" ]] || { echo "Maintenance remains enabled; manual verification required"; exit 1; }
php artisan up
echo "Deployment finished. Record installed revision $target in HMS_RELEASE_REVISION and rebuild config cache."
