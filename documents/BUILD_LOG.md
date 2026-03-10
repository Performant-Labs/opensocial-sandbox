# Build Log — pl-opensocial

This log records every change made to the Open Social site across all implementation phases.
All configuration referenced below is exported to `config/sync/` and can be reproduced with `ddev drush cim -y`.
Custom module code lives in `web/modules/custom/`.

Step numbering uses BASIC-style sparse numbering: Phase 1 = 0-90, Phase 2 = 100s, Phase 3 = 300s, Phase 4 = 500s.
Steps increment by 10 to allow inserting new steps without renumbering.

---

## Config Import Method

Phase configs are stored in `config/phaseN/` directories (where N is the phase number). Individual configs are imported via `ddev drush php:eval` because `ddev drush cim --partial` validates ALL active config (not just the partial import), and pre-existing dependency errors block it.

```php
ddev drush php:eval '
$configs = ["config.name.one", "config.name.two"];
foreach ($configs as $name) {
  $yaml = file_get_contents("/var/www/html/config/phaseN/$name.yml");
  $data = \Drupal\Component\Serialization\Yaml::decode($yaml);
  \Drupal::configFactory()->getEditable($name)->setData($data)->save();
  echo "Imported: $name\n";
}
'
```

---

# Phase 1 — Clean-Room Initialization

**Goal**: Establish a fresh Open Social 13.0.0 environment with correct port pinning and private file system configuration.

**Step 010** — Scaffold Project
Use the official Open Social project template (`social_template`) to ensure all core and contrib patches are applied correctly.
```bash
composer create-project goalgorilla/social_template:13.0.0 . --no-interaction
```

**Step 020** — Configure DDEV
Pin ports to avoid conflicts with other projects and set specific versions. Use `8443` for HTTPS to match standard DDEV/Playwright expectations.
```bash
ddev config --project-name=pl-opensocial-rework --project-type=drupal10 --docroot=web --php-version=8.3 --database=mariadb:11.8 --router-http-port=8080 --router-https-port=8443 --auto
ddev start
mkdir private
```

**Step 030** — Configure Private Path
Append to `web/sites/default/settings.php`:
```php
$settings['file_private_path'] = '/var/www/html/private';
```

**Step 040** — Site Install
```bash
ddev drush site:install social --account-name=admin --account-pass=admin --site-name="Open Social Rework" -y
```

---

# Phase 2 — Content Types & Text Formats

**Goal**: Configure Topic, Event, and Page content types to match g.d.o's feature set.

> All fields listed below are **Open Social defaults** — no new fields were created.
> Phase 2 work consisted entirely of reconfiguring existing fields and text formats.

> [!IMPORTANT]
> **linkit Dependency**: The `full_html` text format configuration references the `linkit` filter. The `linkit` module MUST be enabled before importing the `filter.format.full_html` config, otherwise the import will hang or fail due to missing plugins.
> ```bash
> ddev drush en linkit -y
> ```

> [!NOTE]
> **Why it might look "stuck"**:
> 1. **Config Imports**: If a dependency (like a module or another config) is missing, Drush might hang or output cryptic errors. Always enable dependent modules first.
> 2. **Playwright Installation**: `npx playwright install` downloads large browser binaries (~100MB+). It will look stuck while downloading; please allow 1-3 minutes for completion.
> 3. **Playwright Tests**: If browsers aren't installed or the `baseURL` in `playwright.config.ts` is wrong/unreachable (e.g., wrong port), tests will hang until they hit their 30s timeout.

## Topic → Discussion

**Step 100** — Body field + text format: enable Full HTML and Markdown; remove redundant display filters stripping HTML on render
- Config: [filter.format.full_html.yml](file:///Users/andreangelantoni/Sites/pl-opensocial/config/sync/filter.format.full_html.yml)

**Step 110** — Body field: enable summary/teaser toggle
- Config: [core.entity_form_display.node.topic.default.yml](file:///Users/andreangelantoni/Sites/pl-opensocial/config/sync/core.entity_form_display.node.topic.default.yml)

**Step 120** — Attachment field: 15 MB limit, expanded extensions (`pdf doc docx xls xlsx ppt pptx txt rtf odt ods odp zip gz tar`)
- Config: [field.field.node.topic.field_files.yml](file:///Users/andreangelantoni/Sites/pl-opensocial/config/sync/field.field.node.topic.field_files.yml)

**Step 130** — Wiki-links (`[[title]]`) support
- Copy module: `cp -r ~/Sites/pl-opensocial/web/modules/custom/pl_opensocial_wiki web/modules/custom/`
- Enable: `ddev drush en pl_opensocial_wiki -y`
- `ddev drush cr`

> Note: `pl_opensocial_wiki` is a PSR-4 module containing only `pl_opensocial_wiki.info.yml` and `src/Plugin/`. It does NOT have a `.module` file. The `cp -r` command should place the entire directory at `web/modules/custom/pl_opensocial_wiki/`.

## Event Enhancements

**Step 140** — Enable Event Type sub-module and its field
- `ddev drush en social_event_type -y`
- Config: [field.field.node.event.field_event_type.yml](file:///Users/andreangelantoni/Sites/pl-opensocial/config/sync/field.field.node.event.field_event_type.yml)

> [!IMPORTANT]
> The module MUST be enabled BEFORE importing its field config. If you import the field YAML without the module active, the import will silently fail or error.

**Step 150** — Enable Event Managers sub-module and its field
- `ddev drush en social_event_managers -y`
- Config: [field.field.node.event.field_event_managers.yml](file:///Users/andreangelantoni/Sites/pl-opensocial/config/sync/field.field.node.event.field_event_managers.yml)

> [!IMPORTANT]
> Same as Step 140: enable the module first, then import config.

**Step 160** — Event attachments: 15 MB limit, expanded extensions
- Config: [field.field.node.event.field_files.yml](file:///Users/andreangelantoni/Sites/pl-opensocial/config/sync/field.field.node.event.field_files.yml)

**Step 170** — Create Event Type taxonomy terms
- Vocabulary: `event_type` — [taxonomy.vocabulary.event_type.yml](file:///Users/andreangelantoni/Sites/pl-opensocial/config/sync/taxonomy.vocabulary.event_type.yml)

> [!WARNING]
> `ddev drush term:create` does not exist in the Drush version shipped with Open Social. Use `php:eval` with `Term::create()` instead.

```php
ddev drush php:eval '
foreach ([
  "User group meeting",
  "Drupalcamp or Regional Summit",
  "DrupalCon",
  "Online meeting (e.g. IRC meeting)",
  "Training (free or commercial)",
  "Sprint",
  "Related event (not Drupal-specific)",
] as $name) {
  $term = \Drupal\taxonomy\Entity\Term::create(["vid" => "event_type", "name" => $name]);
  $term->save();
  echo "Created: $name (tid=" . $term->id() . ")\n";
}
'
```
> Terms live in the database, not config YAML. Tids may differ from original (5–11).

**Step 180** — Verify event_type terms exist
```bash
ddev drush php:eval 'echo count(\Drupal::entityTypeManager()->getStorage("taxonomy_term")->loadByProperties(["vid" => "event_type"])) . " event_type terms\n";'
```
> Expected: 7 event_type terms

## Enrollment & Stability Fixes

**Step 182** — Enable Enrollment sub-modules and grant permissions
- `ddev drush en social_event_an_enroll social_event_max_enroll -y`
- Fix permissions (Note: machine names may include spaces):
  - `ddev drush role:perm:add authenticated "add event enrollment entities"`
  - `ddev drush role:perm:add authenticated "manage everything enrollments"`
  - `ddev drush role:perm:add authenticated "view published event enrollment entities"`
  - `ddev drush role:perm:add anonymous "add event enrollment entities"`

**Step 184** — Fix "Unexpected Error" (WSOD) on Event pages
If Event pages crash due to missing `field_event_url` table, manually synchronize storage:
```bash
ddev drush php:eval '\Drupal::entityDefinitionUpdateManager()->installFieldStorageDefinition("field_event_url", "node", "node", \Drupal\field\Entity\FieldStorageConfig::loadByName("node", "field_event_url"));'
```

**Step 186** — Restore missing Frontend Libraries
Ensure `node-waves` and `autosize` are present in `web/libraries/`.
```bash
mkdir -p web/libraries
cp -R ~/Sites/pl-opensocial/web/libraries/node-waves web/libraries/
cp -R ~/Sites/pl-opensocial/web/libraries/autosize web/libraries/
```

> Note: `field_event_url` and `field_event_enroll` are OS defaults and require no config changes.

## Page → Wiki Page

**Step 190** — Edit permissions for authenticated users
- Config: [user.role.authenticated.yml](file:///Users/andreangelantoni/Sites/pl-opensocial/config/sync/user.role.authenticated.yml)

**Step 200** — Revision log enabled by default
- Config: [node.type.page.yml](file:///Users/andreangelantoni/Sites/pl-opensocial/config/sync/node.type.page.yml)

**Step 210** — Page attachments: 15 MB limit, expanded extensions
- Config: [field.field.node.page.field_files.yml](file:///Users/andreangelantoni/Sites/pl-opensocial/config/sync/field.field.node.page.field_files.yml)

**Step 220** — Clear caches after Phase 2 config imports
- `ddev drush cr`

## Phase 2 Tests

> [!IMPORTANT]
> **Test Environment Setup**:
> 1. **Copy Tests**: `cp -r ~/Sites/pl-opensocial/tests ~/Sites/pl-opensocial-rework/tests`
> 2. **Update Config**: Edit `tests/playwright.config.ts` to set `baseURL: 'https://pl-opensocial-rework.ddev.site:8443'`.
> 3. **Install Dependencies**: Run `npm install` in the `tests/` directory.
> 4. **Install Browsers**: Run `npx playwright install chromium`.

> [!NOTE]
> **Open Social 13 Selector Updates**:
> Tests in `phase1-content-types.spec.ts` have been updated to use `.body-text` instead of `.field--name-body`. Title assertions now target `.teaser__title h1` or `.block-page-title-block h1` to avoid conflicts with `h1` tags within Markdown content.

**Step 230** — Run (from the `tests/` directory): `./node_modules/.bin/playwright test e2e/phase1-content-types.spec.ts --reporter=list --timeout=60000`

---

**Step 240** — Pre-Phase 3 backup: `ddev export-db --file=backups/phase3-pre.sql.gz`

# Phase 3 — Group Structure & Membership

**Goal**: Configure Open Social's `flexible_group` to replicate g.d.o's group types, membership models, archive enforcement, moderation queue, and submission guidelines.

## Group Type Taxonomy Terms

Vocabulary: `group_type` (Open Social default) — [taxonomy.vocabulary.group_type.yml](file:///Users/andreangelantoni/Sites/pl-opensocial/config/sync/taxonomy.vocabulary.group_type.yml)

> [!WARNING]
> `ddev drush term:create` does not exist in this Drush version. Use `php:eval` with `Term::create()`.

**Step 300** — Create group_type terms
```php
ddev drush php:eval '
$terms = [
  ["Geographical", "Local user groups by city/region"],
  ["Working group", "Module, feature, or initiative coordination"],
  ["Distribution", "Drupal distribution projects"],
  ["Event planning", "DrupalCon and camp organising"],
  ["Archive", "Inactive groups (read-only)"],
];
foreach ($terms as [$name, $desc]) {
  $term = \Drupal\taxonomy\Entity\Term::create(["vid" => "group_type", "name" => $name, "description" => ["value" => $desc, "format" => "plain_text"]]);
  $term->save();
  echo "Created: $name (tid=" . $term->id() . ")\n";
}
'
```
> Terms live in the database, not config YAML. Tids may differ from original (12–16).

## Membership Models

**Step 310** — No configuration changes needed. Open Social's `flexible_group` already supports all g.d.o models:

| g.d.o model | `field_group_allowed_join_method` | `field_flexible_group_visibility` |
|---|---|---|
| Open | `direct` | `public` |
| Moderated | `request` | `public` or `community` |
| Invite only | `invite` | `secret` |
| Closed | `invite` | `secret` |

## Group Directory

**Step 320** — Modify `newest_groups` view: filter out secret groups by default
- Config: [views.view.newest_groups.yml](file:///Users/andreangelantoni/Sites/pl-opensocial/config/sync/views.view.newest_groups.yml)

## Custom Module: `pl_group_extras`

**Step 330** — Copy module to `web/modules/custom/pl_group_extras/`
- `cp -r ~/Sites/pl-opensocial/web/modules/custom/pl_group_extras web/modules/custom/`
- Contents: `pl_group_extras.info.yml`, `pl_group_extras.module`, `pl_group_extras.libraries.yml`, `css/pl_group_extras.css`

**Step 340** — Enable: `ddev drush en pl_group_extras -y`

Hooks implemented:
- `hook_form_alter()` — blocks content creation in archived groups; injects submission guidelines
- `hook_entity_presave()` — sets non-admin groups to unpublished (moderation queue)
- `hook_entity_insert()` — sends email notification for pending groups
- `hook_preprocess_group()` — adds "Archived" badge
- `hook_node_access()` — denies content creation in archived groups

## Pending Groups View

**Step 350** — Import `pending_groups` view at `/admin/groups/pending`
- Config: [views.view.pending_groups.yml](file:///Users/andreangelantoni/Sites/pl-opensocial/config/sync/views.view.pending_groups.yml)
- Note: the `operations` field was removed after initial deployment due to a broken `entity_operations` plugin. The exported YAML already has this fix.

**Step 360** — Clear caches after Phase 3 config imports
- `ddev drush cr`

## Phase 3 Tests

**Step 370** — Run (from the `tests/` directory): `./node_modules/.bin/playwright test e2e/phase2-groups.spec.ts --reporter=list`
- 13 tests: group creation, directory filtering, archiving, moderation queue, guidelines.

---

**Step 380** — Pre-Phase 4 backup: `ddev export-db --file=backups/phase4-pre.sql.gz`

# Phase 4 — Content Discovery & Aggregation

**Goal**: Implement tags, events calendar, iCal feeds, hot content scoring, promoted content, and RSS feeds.

## Enable Contrib Modules

**Step 500** — `ddev drush en social_tagging -y`

**Step 510** — `ddev drush en statistics -y`

**Step 520** — `ddev drush en flag -y`

**Step 530** — `ddev drush en flag_count -y`

Config: [core.extension.yml](file:///Users/andreangelantoni/Sites/pl-opensocial/config/sync/core.extension.yml) (lists all enabled modules)

## Tags Aggregation View

**Step 540** — Import `tags_aggregation` view at `/tags`
- Config: [views.view.tags_aggregation.yml](file:///Users/andreangelantoni/Sites/pl-opensocial/config/sync/views.view.tags_aggregation.yml)

## Custom Module: `pl_discovery`

**Step 550** — Copy module to `web/modules/custom/pl_discovery/`
- `cp -r ~/Sites/pl-opensocial/web/modules/custom/pl_discovery web/modules/custom/`

**Step 560** — Enable: `ddev drush en pl_discovery -y`
- Creates DB table `pl_discovery_hot_score` via `hook_schema()`
- Schema: `nid` INT PK, `score` FLOAT, `computed` INT

Hooks:
- `hook_cron()` — recomputes hot scores
- `hook_views_data()` — exposes `pl_discovery_hot_score` to Views
- `hook_node_insert()` — seeds hot score entries

## Hot Content View

**Step 570** — Import `hot_content` view at `/hot`
- Config: [views.view.hot_content.yml](file:///Users/andreangelantoni/Sites/pl-opensocial/config/sync/views.view.hot_content.yml)

## Promoted Content

**Step 580** — Import `promote_homepage` flag
- Config: [flag.flag.promote_homepage.yml](file:///Users/andreangelantoni/Sites/pl-opensocial/config/sync/flag.flag.promote_homepage.yml)

**Step 590** — Import `promoted_content` view at `/admin/content/promoted`
- Config: [views.view.promoted_content.yml](file:///Users/andreangelantoni/Sites/pl-opensocial/config/sync/views.view.promoted_content.yml)

**Step 600** — Grant flag permissions to `contentmanager` and `sitemanager` roles
- Config: [user.role.contentmanager.yml](file:///Users/andreangelantoni/Sites/pl-opensocial/config/sync/user.role.contentmanager.yml), [user.role.sitemanager.yml](file:///Users/andreangelantoni/Sites/pl-opensocial/config/sync/user.role.sitemanager.yml)

## Group RSS Feed

**Step 610** — Import `group_rss_feed` view for group activity RSS
- Config: [views.view.group_rss_feed.yml](file:///Users/andreangelantoni/Sites/pl-opensocial/config/sync/views.view.group_rss_feed.yml)

**Step 620** — Clear caches after Phase 4 config imports
- `ddev drush cr`

## Phase 4 Tests

**Step 630** — Run (from the `tests/` directory): `./node_modules/.bin/playwright test e2e/phase3-discovery.spec.ts --reporter=list`

---

# How to Reproduce This Site

Starting from a vanilla Open Social 13.0.0 installation (see **Phase 1**):

```bash
# 1. Import all configuration
#    Note: ddev drush cim --partial may fail due to pre-existing config
#    dependency errors. Use the php:eval method documented above in
#    "Config Import Method" for per-file imports.
ddev drush cim -y

# 2. Enable custom modules (creates DB tables, registers hooks)
ddev drush en pl_opensocial_wiki pl_group_extras pl_discovery -y

# 3. Create taxonomy terms (not stored in config)
#    NOTE: ddev drush term:create does NOT exist in this Drush version.
#    Use php:eval with Term::create() — see Steps 170 and 300 above.
ddev drush php:eval '
foreach (["Geographical", "Working group", "Distribution", "Event planning", "Archive"] as $name) {
  \Drupal\taxonomy\Entity\Term::create(["vid" => "group_type", "name" => $name])->save();
}
foreach (["User group meeting", "Drupalcamp or Regional Summit", "DrupalCon", "Online meeting (e.g. IRC meeting)", "Training (free or commercial)", "Sprint", "Related event (not Drupal-specific)"] as $name) {
  \Drupal\taxonomy\Entity\Term::create(["vid" => "event_type", "name" => $name])->save();
}
echo "Done\n";
'

# 4. Clear caches
ddev drush cr
```

---

# Schema Inventory

## Entity Fields

> **No custom Drupal fields have been added.** All fields on Topic, Event, Page, and Group are Open Social defaults. Inspect `field.field.*` and `field.storage.*` in `config/sync/`.

## Group Relationship Types (all Open Social defaults)

| Machine name | Plugin | Purpose |
|---|---|---|
| `flexible_group-group_membership` | `group_membership` | User membership |
| `flexible_group-group_node-event` | `group_node:event` | Event nodes in groups |
| `flexible_group-group_node-topic` | `group_node:topic` | Topic nodes in groups |
| `flexible_group-group_invitation` | `group_invitation` | Group invitations |
| `group_content_type_7fcb76fdf61a9` | `group_membership_request` | Membership requests |
