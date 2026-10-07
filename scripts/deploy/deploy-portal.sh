#!/usr/bin/env bash
# Runs ON the VM (portal-kong-runtime), as root via sudo, streamed by GitHub Actions.
# Equivalent to the manual routine:
#   git pull && drush updb && drush cim && drush cr
# plus `composer install` when composer.json/lock changed (vendor/ is gitignored).
#
# Can also be run by hand on the VM:
#   sudo bash scripts/deploy/deploy-portal.sh
set -Eeuo pipefail
# sudo resets PATH on RHEL/CentOS; composer and php often live in /usr/local/bin.
export PATH="/usr/local/bin:/usr/local/sbin:$PATH"

APP_DIR="${APP_DIR:-/var/www/html/shushportal}"
DEPLOY_BRANCH="${DEPLOY_BRANCH:-development}"
EXPECTED_SHA="${EXPECTED_SHA:-}"
FORCE_COMPOSER="${FORCE_COMPOSER:-0}"
# Run git/composer/drush as whoever owns the checkout (same as a manual deploy).
DEPLOY_USER="${DEPLOY_USER:-$(stat -c %U "$APP_DIR")}"
DEPLOY_HOME="${DEPLOY_HOME:-$(getent passwd "$DEPLOY_USER" | cut -d: -f6)}"
DRUSH="$APP_DIR/vendor/bin/drush"
LOCK_FILE=/tmp/shushportal-deploy.lock
# Backups (one copy each, replaced on every deploy). Kept outside the web root.
BACKUP_DIR="${BACKUP_DIR:-/var/backups/shushportal}"
SKIP_BACKUP="${SKIP_BACKUP:-0}"

log()  { printf '\n\033[1;34m[%s] ==> %s\033[0m\n' "$(date -u +%H:%M:%SZ)" "$*"; }
fail() { printf '\n\033[1;31mDEPLOY FAILED: %s\033[0m\n' "$*" >&2; exit 1; }
trap 'fail "command failed on line $LINENO: $BASH_COMMAND"' ERR

# Run a command as the checkout owner, from APP_DIR.
as_app() { sudo -u "$DEPLOY_USER" env HOME="$DEPLOY_HOME" PATH="$PATH" COMPOSER_ALLOW_SUPERUSER=1 "$@"; }

[ -d "$APP_DIR/.git" ] || fail "$APP_DIR is not a git checkout"
cd "$APP_DIR"

exec 9>"$LOCK_FILE"
flock -n 9 || fail "another deploy is already running on this VM"

log "Deploying $DEPLOY_BRANCH to $APP_DIR as user '$DEPLOY_USER'"

branch="$(as_app git rev-parse --abbrev-ref HEAD)"
[ "$branch" = "$DEPLOY_BRANCH" ] || fail "server checkout is on '$branch', expected '$DEPLOY_BRANCH'"

if [ -n "$(as_app git status --porcelain --untracked-files=no)" ]; then
  echo "WARNING: tracked files are modified on the server:"
  as_app git status --short --untracked-files=no
fi

prev_sha="$(as_app git rev-parse HEAD)"

# ---------------------------------------------------------------------------
# Backup: code (zip) + database (gzipped SQL dump via drush, which reads the
# DB host/credentials from settings.php -> portal-kong-db). Only the latest
# copy of each is kept. New files are written to *.tmp first and swapped in
# only on success, so a failed backup never destroys the previous good one.
# Any backup failure aborts the deploy before code or DB is touched.
# ---------------------------------------------------------------------------
if [ "$SKIP_BACKUP" = "1" ]; then
  log "Backup: SKIPPED (SKIP_BACKUP=1)"
else
  command -v zip >/dev/null || fail "zip is not installed on the VM (dnf install -y zip)"
  [ -x "$DRUSH" ] || fail "drush not found at $DRUSH; cannot back up the database"
  install -d -m 750 -o "$DEPLOY_USER" "$BACKUP_DIR"

  code_zip="$BACKUP_DIR/shushportal-code.zip"
  db_dump="$BACKUP_DIR/shushportal-db.sql"          # drush adds .gz
  rm -f "$code_zip.tmp" "$db_dump.tmp" "$db_dump.tmp.gz"

  log "Backup: database -> $db_dump.gz"
  as_app "$DRUSH" sql:dump --gzip --result-file="$db_dump.tmp" \
    || fail "database backup failed (is mysqldump installed and can this VM reach portal-kong-db?)"
  gzip -t "$db_dump.tmp.gz" || fail "database backup is corrupt"
  mv -f "$db_dump.tmp.gz" "$db_dump.gz"

  log "Backup: code $APP_DIR -> $code_zip"
  ( cd "$(dirname "$APP_DIR")" && zip -qry "$code_zip.tmp" "$(basename "$APP_DIR")" ) \
    || fail "code backup failed"
  mv -f "$code_zip.tmp" "$code_zip"

  {
    echo "date_utc=$(date -u +%Y-%m-%dT%H:%M:%SZ)"
    echo "branch=$DEPLOY_BRANCH"
    echo "commit_before_deploy=$prev_sha"
    echo "deploying_commit=${EXPECTED_SHA:-unknown}"
  } > "$BACKUP_DIR/backup-info.txt"
  chmod 640 "$code_zip" "$db_dump.gz" "$BACKUP_DIR/backup-info.txt"
  ls -lh "$BACKUP_DIR"
fi

log "git pull (fast-forward only)"
as_app git fetch --prune origin "$DEPLOY_BRANCH"
as_app git merge --ff-only "origin/$DEPLOY_BRANCH" \
  || fail "cannot fast-forward; the server checkout has diverged from origin/$DEPLOY_BRANCH"
new_sha="$(as_app git rev-parse HEAD)"
echo "  $prev_sha -> $new_sha"

if [ -n "$EXPECTED_SHA" ] && ! as_app git merge-base --is-ancestor "$EXPECTED_SHA" HEAD; then
  fail "commit $EXPECTED_SHA is not in the deployed history"
fi

need_composer=0
[ "$FORCE_COMPOSER" = "1" ] && need_composer=1
[ -x "$DRUSH" ] || need_composer=1
if [ "$prev_sha" != "$new_sha" ] && \
   [ -n "$(as_app git diff --name-only "$prev_sha" "$new_sha" -- composer.json composer.lock)" ]; then
  need_composer=1
fi

if [ "$need_composer" = "1" ]; then
  log "composer install (composer.json/lock changed)"
  command -v composer >/dev/null || fail "composer is not installed on the VM"
  as_app composer install --no-interaction --no-progress --optimize-autoloader
else
  log "composer: no dependency changes, skipping"
fi

# Drupal's recommended order (same as `drush deploy`): updb before cim.
log "drush updb"
as_app "$DRUSH" updatedb -y

log "drush cim"
as_app "$DRUSH" config:import -y

log "drush cr"
as_app "$DRUSH" cache:rebuild

log "Status"
as_app "$DRUSH" status --fields=drupal-version,bootstrap,db-status

trap - ERR
printf '\n\033[1;32mDeploy OK: %s now at %s\033[0m\n' "$DEPLOY_BRANCH" "$(as_app git log -1 --format='%h %s')"
