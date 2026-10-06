# CI/CD: `development` → portal-kong-runtime

Every push to `development` runs `.github/workflows/deploy-development.yml`, which:

1. Logs in to GCP with keyless auth (Workload Identity Federation). Only this repo's `development` branch is allowed.
2. SSHes into `portal-kong-runtime` (project `sherlock-004`) through an IAP tunnel, so the VM doesn't need a public SSH port.
3. Runs `deploy-portal.sh` on the VM:
   - `git fetch` + fast-forward merge of `development` (fails if the server copy has diverged)
   - `composer install`, but only when `composer.json`/`composer.lock` changed (`vendor/` isn't in git)
   - `drush updb -y` → `drush cim -y` → `drush cr`
   - `drush status`

Deploys are queued, never run in parallel, and a lock on the VM prevents overlap with a manual run.
You can also trigger a deploy by hand: **Actions → Deploy development → Run workflow**. Tick *force_composer* to run composer install anyway.

## One-time setup

1. **GCP** (from your laptop, logged in as a project admin):
   ```bash
   gcloud auth login
   bash scripts/deploy/gcp-setup.sh
   ```
   At the end it prints the two secrets and one variable to add.
2. **GitHub** → repo Settings → Secrets and variables → Actions:
   - Secret `GCP_WIF_PROVIDER`
   - Secret `GCP_DEPLOY_SA`
   - Variable `GCE_ZONE`
3. **VM git access**: the checkout's owner must be able to `git fetch` without a prompt. Test it on the VM:
   ```bash
   sudo -u $(stat -c %U /var/www/html/shushportal) git -C /var/www/html/shushportal fetch origin
   ```
   If it asks for credentials, add a read-only **Deploy key** (repo Settings → Deploy keys) for that user,
   and point the remote at SSH: `git remote set-url origin git@github.com:shushinc/shushportal.git`.
4. Make sure the server checkout is on `development`: `git -C /var/www/html/shushportal branch --show-current`.

## Overrides (environment variables for deploy-portal.sh)

| Var | Default |
|---|---|
| `APP_DIR` | `/var/www/html/shushportal` |
| `DEPLOY_USER` | owner of `APP_DIR` |
| `DEPLOY_HOME` | that user's home (set to `$APP_DIR` if drush complains about HOME, as `localInstallation` does) |
| `FORCE_COMPOSER` | `0` |

## Backups (every deploy, before `git pull`)

Stored on portal-kong-runtime in `/var/backups/shushportal/`. Only the latest copy is kept; each deploy replaces it.

| File | What |
|---|---|
| `shushportal-code.zip` | the whole `/var/www/html/shushportal` folder as it was before the deploy (incl. `vendor/`, `.git`) |
| `shushportal-db.sql.gz` | `drush sql:dump` of the portal DB on portal-kong-db (credentials come from `settings.php`) |
| `backup-info.txt` | timestamp and the commit the backup corresponds to |

If a backup fails, the deploy stops before changing anything. To skip once (not recommended): run the script with `SKIP_BACKUP=1`.

### Restore
```bash
# code
cd /var/www/html && mv shushportal shushportal.broken && unzip -q /var/backups/shushportal/shushportal-code.zip
# database
cd /var/www/html/shushportal && zcat /var/backups/shushportal/shushportal-db.sql.gz | vendor/bin/drush sql:cli
vendor/bin/drush cr
```
