# Installation Guide

Instructions for setting up the `pl-opensocial` project locally after a fresh `git clone`.

---

## Prerequisites

| Tool | Minimum version | Notes |
|---|---|---|
| [DDEV](https://ddev.com) | 1.22+ | Local development environment |
| [OrbStack](https://orbstack.dev) or Docker Desktop | — | Container runtime; OrbStack recommended on macOS |
| [Composer](https://getcomposer.org) | 2.x | Only needed if running Composer outside DDEV |

All PHP, MariaDB, and Nginx dependencies are managed inside DDEV containers — nothing needs to be installed on the host beyond DDEV and a container runtime.

---

## Steps

### 1. Clone the repository

```bash
git clone <repo-url> pl-opensocial
cd pl-opensocial
```

> The second argument (`pl-opensocial`) sets the target directory name. If you clone into a differently named directory you **must** edit `.ddev/config.yaml` and change the `name:` field to match, otherwise DDEV will use the old project's hostname and database.

### 2. Start DDEV

```bash
ddev start
```

This starts the web (PHP 8.3, Nginx), database (MariaDB 11.8), and Mailpit containers and configures the `https://pl-opensocial.ddev.site` hostname.

### 3. Install Composer dependencies

```bash
ddev composer install
```

This installs everything declared in `composer.lock`:

- Drupal core (`web/core/`)
- Open Social 13 profile (`web/profiles/contrib/`)
- All contributed modules and themes (`web/modules/contrib/`, `web/themes/contrib/`)
- npm/JS assets via asset-packagist (`web/libraries/`)
- Drush (`vendor/bin/drush`)

> This step downloads several hundred packages. On a slow connection it can take 5–10 minutes.

### 4. Create the private files directory

```bash
mkdir -p private
```

This directory sits outside the webroot and is already referenced in `web/sites/default/settings.php`:

```php
$settings['file_private_path'] = '/var/www/html/private';
```

Open Social requires this to be present before installation will proceed.

### 5. Install the site

```bash
ddev drush site:install social \
  --account-name=admin \
  --account-pass=admin \
  --site-name="Open Social" \
  --site-mail=admin@example.com \
  --locale=en \
  -y
```

This runs Drupal's site installation using Open Social's `social` install profile. It will:

- Create all database tables
- Enable all Open Social modules
- Import default configuration
- Create the `admin` user

> **Expected warnings during install**: Solr-related warnings are normal — Open Social ships `search_api_solr` but no Solr server is configured. These are non-blocking and can be ignored.

### 6. Run post-install steps

```bash
ddev post-install
```

This custom DDEV command (`.ddev/commands/web/post-install`) handles several compatibility issues between Open Social's `site:install` and the exported config/sync:

- Sets the site UUID to match the one in `config/sync/system.site.yml`
- Removes seed entities (taxonomy terms, profile, crop, block_content) created by `site:install` that would block config import
- Pre-creates two field storages (`field_activity_entity`, `field_grequest_message`) that are not installed by `site:install` but are required by the config
- Fixes UUID mismatches for config entities (block_content types, language entity) to avoid destructive delete+recreate operations during import
- Runs `drush config:import` (twice, to handle config dependency ordering)
- Runs `drush cache:rebuild`

### 7. Open the site

```bash
ddev launch
```

Or navigate manually to: **`https://pl-opensocial.ddev.site`**

Log in with:

- Username: `admin`
- Password: `admin`

---

## Daily workflow

| Task | Command |
|---|---|
| Start containers | `ddev start` |
| Stop containers | `ddev stop` |
| Drush commands | `ddev drush <command>` |
| Composer commands | `ddev composer <command>` |
| SSH into web container | `ddev ssh` |
| View Mailpit (caught emails) | `ddev launch -m` or `https://pl-opensocial.ddev.site:8026` |
| View database | `ddev describe` → use the listed DB port with TablePlus/Sequel Pro |
| Rebuild caches | `ddev drush cr` |
| Run database updates | `ddev drush updb -y` |
| Export config | `ddev drush config:export -y` |
| Import config | `ddev drush config:import -y` |

---

## Known issues

### `settings.ddev.php` is not committed

DDEV auto-generates `web/sites/default/settings.ddev.php` on `ddev start`. It injects the database credentials and is intentionally excluded from the repository. Never commit it.

### Solr warnings during install

Search API Solr warnings are expected. The module is bundled with Open Social but no Solr container is configured in this DDEV setup. Site installation completes successfully despite the warnings.

### macOS `.DS_Store` files

If `ddev composer create-project` or similar commands fail with a "directory is not empty" error, check for a `.DS_Store` file:

```bash
rm -f .DS_Store
```

### MentionsFilter PHP 8 compatibility patch

Open Social's `mentions` module declares a typed property (`$textFormat`) without initialising it to `null`, which throws a fatal error in PHP 8.x when the property is accessed before `setTextFormat()` is called. A one-line patch is applied automatically by `composer install` via `patches/mentions-filter-php8-fix.patch`.

---

## Re-installing from scratch

To wipe the database and start over without re-running `composer install`:

```bash
ddev drush site:install social \
  --account-name=admin \
  --account-pass=admin \
  --site-name="Open Social" \
  -y
ddev post-install
```
