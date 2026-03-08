# Rework Plan — Clean-Room Rebuild

Rebuild the Open Social site from scratch at `~/Sites/pl-opensocial-rework` using only programmatic methods (drush commands, config YAML imports — **no admin UI**), proving the BUILD_LOG is reproducible.

The existing site at `~/Sites/pl-opensocial` remains **untouched** throughout this process.

---

## Environment

| Component | Version | Source |
|---|---|---|
| Open Social | `13.0.0` | `composer create-project goalgorilla/social_template` |
| Drupal | `10.6.4-dev` | Ships with OS 13.0.0 |
| PHP | `8.3` | DDEV config |
| MariaDB | `11.8` | DDEV config |
| Webserver | `nginx-fpm` | DDEV config |
| DDEV project name | `pl-opensocial-rework` | New instance, new ports |

---

## Step 0 — Project Setup

1. `mkdir ~/Sites/pl-opensocial-rework && cd ~/Sites/pl-opensocial-rework`
2. `composer create-project goalgorilla/social_template:13.0.0 .` *(must run before `git init` — composer requires empty directory)*
3. `git init && git add -A && git commit -m "Initial Open Social 13.0.0 scaffold"`
4. `ddev config --project-name=pl-opensocial-rework --project-type=drupal10 --docroot=web --php-version=8.3 --mariadb-version=11.8`
5. Pin unique HTTPS/HTTP ports in `.ddev/config.yaml`:
   ```yaml
   router_http_port: 8580
   router_https_port: 8543
   ```
6. `ddev start`
7. Install Open Social with known admin credentials:
   ```bash
   ddev drush site:install social --account-name=admin --account-pass=admin --yes
   ```
8. Verify: `ddev drush status`
9. Verify `.gitignore` is present (ships with `social_template`) — should exclude `vendor/`, `web/core/`, `web/modules/contrib/`, etc.

## Step 1 — Copy Tests

1. Copy test files from `pl-opensocial`:
   ```bash
   cp -r ~/Sites/pl-opensocial/tests ~/Sites/pl-opensocial-rework/tests
   ```
2. Update `tests/playwright.config.ts`:
   - Change `baseURL` to `https://pl-opensocial-rework.ddev.site:8543`
   - Update any Mailpit URL from `pl-opensocial.ddev.site:8302` to the new DDEV Mailpit port
3. `cd tests && npm install`
4. Verify Playwright can connect: `npx playwright test --list`

> **⚠️ Note**: Check that the Mailpit test in `phase2-groups.spec.ts` (line ~510) has its URL updated. The test has a graceful fallback (`console.log('Mailpit API not accessible, skipping email verification')`) so it won't hard-fail, but it should be pointed at the right URL to be a real test.

## Step 2 — Create Delta Config Directories

From `pl-opensocial`, extract only the config YAMLs that differ from vanilla OS defaults into per-phase directories:

```
pl-opensocial-rework/
  config/
    phase1/   # Text format, field settings, form display, permissions, node type
    phase2/   # Views (pending_groups, newest_groups)
    phase3/   # Views (tags, hot, promoted, rss), flag, roles
```

These directories are **kept permanently** so a developer can examine exactly what changed per phase.

**Method**: For each phase, diff the `pl-opensocial/config/sync/` files against a vanilla OS install's exported config to identify the deltas. Copy only the changed files.

> **⚠️ Warning — UUID noise**: Drupal generates unique UUIDs and `_core.default_config_hash` values per install. When diffing configs, **ignore** `uuid` and `_core` lines. Use:
> ```bash
> diff <(grep -v -E '^uuid:|^  default_config_hash:' file1.yml) \
>      <(grep -v -E '^uuid:|^  default_config_hash:' file2.yml)
> ```
> Only real setting differences matter.

---

## Per-Phase Procedure

Repeat for each phase (1, 2, 3):

### A. Backup

```bash
ddev export-db --file=backups/phase{N}-pre-$(date +%Y%m%d-%H%M).sql.gz
```

Name format: `phase1-pre-20260308-1530.sql.gz`

### B. Execute BUILD_LOG Steps

Follow BUILD_LOG Phase N instructions using **only**:
- `ddev drush en {module} -y` for module enables
- `ddev drush config:import --partial --source=config/phase{N}/` for config changes
- `ddev drush term:create` for taxonomy terms
- `ddev drush php:eval` for any one-off PHP
- Copy custom modules: `cp -r ~/Sites/pl-opensocial/web/modules/custom/{module} web/modules/custom/`
- `ddev drush cr` to clear caches after every batch of imports

**No admin UI clicks.**

### C. Compare Against Reference Site

Before running tests, compare the new site's config against the original.

```bash
# Export new site config to a temp directory
ddev drush cex -y --destination=../config/rework-export

# Compare only the files relevant to this phase (not the entire config)
# Strip UUIDs and _core hashes before diffing:
for f in config/phase{N}/*.yml; do
  base=$(basename "$f")
  diff <(grep -v -E '^uuid:|^  default_config_hash:' ~/Sites/pl-opensocial/config/sync/"$base") \
       <(grep -v -E '^uuid:|^  default_config_hash:' config/rework-export/"$base")
done
```

Investigate any unexpected differences. Fix before proceeding.

### D. Run Phase Tests

```bash
cd tests
npx playwright test e2e/phase{N}-*.spec.ts --reporter=list
```

### E. On Failure

1. Consult `IMPLEMENTATION_PLAN.md` Phase N section
2. Review prior session conversations for that phase
3. If needed, roll back: `ddev import-db --file=backups/phase{N}-pre-*.sql.gz`
4. Fix and retry

### F. On Success

1. Update BUILD_LOG with any corrections discovered during this phase
2. Commit: `git add -A && git commit -m "Phase {N} complete — Steps {first}–{last}"`
3. Post-phase backup: `ddev export-db --file=backups/phase{N}-post-$(date +%Y%m%d-%H%M).sql.gz`

### G. Check In With User

**Stop and report to the user** before proceeding to the next phase. Summarise:
- Which steps were executed
- Any corrections made to BUILD_LOG
- Test results (pass/fail counts)
- Any issues encountered and how they were resolved

**Do not proceed to the next phase without user approval.**

---

## Completion Criteria

- All 3 phases complete
- All Playwright tests pass (`phase1-*.spec.ts`, `phase2-*.spec.ts`, `phase3-*.spec.ts`)
- BUILD_LOG updated with any corrections
- Delta config directories preserved in `config/phase{1,2,3}/`
- All backups preserved in `backups/`
- Config comparison against `pl-opensocial` shows no unexpected differences (excluding UUIDs)
