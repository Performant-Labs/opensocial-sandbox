# OpenSocial Sandbox

A sandbox installation of [Open Social 13](https://github.com/goalgorilla/open_social) on Drupal 10, intended to experiment with group collaboration features with the eventual goal of informing a rebuild of [groups.drupal.org](https://groups.drupal.org).

The project explores how Open Social's groups, discussions, events, and wiki pages can be configured and extended to replicate and improve upon the features of the current groups.drupal.org site, which is built on Drupal 7 and the Organic Groups module.

## Requirements

- [DDEV](https://ddev.com) 1.22+
- [OrbStack](https://orbstack.dev) or Docker Desktop

## Installation

```bash
# Clone the repository
git clone https://github.com/Performant-Labs/opensocial-sandbox.git
cd opensocial-sandbox

# Start DDEV
ddev start

# Install Composer dependencies
ddev composer install

# Create the private files directory
mkdir -p private

# Install the site
ddev drush site:install social \
  --db-url=mysql://root:root@db:3306/social \
  --account-name=admin \
  --account-pass=admin \
  --site-name="Open Social" \
  -y

# Open the site
ddev launch
```

Log in at `https://opensocial-sandbox.ddev.site` with username `admin` and password `admin`.

For full details including daily workflow commands and known issues, see [documents/INSTALL.md](documents/INSTALL.md).

## Documentation

| Document | Description |
| --- | --- |
| [documents/INSTALL.md](documents/INSTALL.md) | Full local setup instructions |
| [documents/IMPLEMENTATION_PLAN.md](documents/IMPLEMENTATION_PLAN.md) | Seven-phase plan for configuring Open Social to match groups.drupal.org features, with Playwright e2e test specs per phase |

## License

GPL-2.0-or-later — the licence used by both [Drupal](https://www.drupal.org/about/licensing) and [Open Social](https://github.com/goalgorilla/open_social/blob/main/LICENSE.txt). See [LICENSE.txt](LICENSE.txt).
