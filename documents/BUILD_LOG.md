# Build Log — pl-opensocial

> [!CAUTION]
> **READ FIRST**: Before executing ANY commands in this log, read [HANGING_PROCESSES.md](file:///Users/andreangelantoni/Sites/pl-opensocial/documents/HANGING_PROCESSES.md). It documents 21 types of hangs encountered during this build — including silent agent approval gates, `networkidle` freezes, PHP opcache stale classes, and DDEV port conflicts. Every one of these has caused lost time.

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

> [!CAUTION]
> **DDEV commands do NOT hang.** All `ddev drush`, `ddev exec`, and `ddev export-db` commands complete in seconds (typically 2-15s). If a terminal session appears "stuck" on a DDEV command, the problem is a **zombie shell session** from a cancelled tool call — not a stuck command. Verify by running `docker ps` to ensure containers are healthy, then re-run the command in a new terminal. Do NOT use the `timeout` command with `ddev` — it sends SIGINT (exit code 130) which kills the process prematurely.

---

# Phase 1 — Clean-Room Initialization

> [!IMPORTANT]
> **DDEV Pre-Flight Check**: Before starting ANY phase:
> 1. **Verify DDEV is running**: `ddev describe` — if it fails, run `ddev start`
> 2. **Stop conflicting projects**: `ddev list` — if `pl-opensocial` (or any other project) is running, stop it: `cd ~/Sites/pl-opensocial && ddev stop`
> 3. **Confirm the port**: Check the HTTPS URL in `ddev describe` output. If the port differs from `8493`, update `tests/playwright.config.ts` to match.
> 4. **Kill zombies**: `bash ~/Sites/pl-opensocial/scripts/kill-zombies.sh`

**Goal**: Establish a fresh Open Social 13.0.0 environment with correct port pinning and private file system configuration.

**Step 010** — Create and enter the project directory
```bash
mkdir pl-opensocial-rework && cd pl-opensocial-rework
ddev config --project-type=drupal10 --docroot=web --php-version=8.3
ddev start
rm -f .DS_Store
```

**Step 020** — Download Open Social via Composer
```bash
ddev composer create-project drupal/recommended-project:^10 . --no-interaction --stability=dev
ddev composer config repositories.asset-packagist '{"type": "composer", "url": "https://asset-packagist.org"}'
ddev composer config allow-plugins.tbachert/spi true
ddev composer config allow-plugins.cweagans/composer-patches true
ddev composer config allow-plugins.oomphinc/composer-installers-extender true
ddev composer config --json extra.enable-patching true
ddev composer require goalgorilla/open_social:^13 drush/drush -W --no-interaction
```
> [!IMPORTANT]
> **Resource Intensive**: Step 020 is heavy on RAM/CPU. If the session crashes or hangs, try `ddev composer install` to resume the download without recalculating the dependency tree.

**Step 030** — Configure the private directory
```bash
mkdir -p private
chmod +w web/sites/default/settings.php
echo "\n\$settings['file_private_path'] = '/var/www/html/private';" >> web/sites/default/settings.php
```
> [!IMPORTANT]
> **Installer Requirement**: The `file_private_path` MUST be set in `settings.php` BEFORE running the site installer in Step 040. If missing, the Open Social installer will fail.

**Step 040** — Site Install and Launch
```bash
ddev drush -y site-install social
ddev drush php:eval 'node_access_rebuild();'
ddev launch
```

---

# Phase 2 — Content Types & Text Formats

> [!IMPORTANT]
> **Zombie cleanup**: Before starting this phase, run the cleanup script:
> ```bash
> bash ~/Sites/pl-opensocial/scripts/kill-zombies.sh
> ```

**Goal**: Configure Topic, Event, and Page content types to match g.d.o's feature set.

> All fields listed below are **Open Social defaults** — no new fields were created.
> Phase 2 work consisted entirely of reconfiguring existing fields and text formats.

> [!IMPORTANT]
> **linkit Dependency**: The `full_html` text format configuration references the `linkit` filter. The `linkit` module is NOT bundled with Open Social and must be installed via Composer first:
> ```bash
> ddev composer require drupal/linkit --no-interaction
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
- Copy module: `cp -R ~/Sites/pl-opensocial/web/modules/custom/pl_opensocial_wiki web/modules/custom/`
- Enable: `ddev drush en pl_opensocial_wiki -y`
- `ddev drush cr`
- **`ddev restart`** (required to flush PHP opcode cache so the web process can find the new class)

> [!CAUTION]
> The `cp -R` command MUST place the entire directory at `web/modules/custom/pl_opensocial_wiki/` (not flat files in `web/modules/custom/`). Verify with `ls web/modules/custom/pl_opensocial_wiki/src/Plugin/Filter/WikiLinkFilter.php`. If the module is installed with the wrong directory structure, the entire site will crash with `PluginException: class does not exist`.
>
> After enabling the module, you MUST run `ddev restart` to flush the PHP opcode cache. A `ddev drush cr` alone is not sufficient — the web process caches class paths separately from Drush CLI.

## Event Enhancements

**Step 140** — Enable Event Type sub-module and its field
- `ddev drush en social_event_type -y`
- Config: [field.field.node.event.field_event_type.yml](file:///Users/andreangelantoni/Sites/pl-opensocial/config/sync/field.field.node.event.field_event_type.yml)

> [!IMPORTANT]
> The module MUST be enabled BEFORE importing its field config. If you import the field YAML without the module active, the import will silently fail or error.

**Step 145** — Import the Event form display (places event_type, event_managers, and other fields on the Event form)
- Config: [core.entity_form_display.node.event.default.yml](file:///Users/andreangelantoni/Sites/pl-opensocial/config/sync/core.entity_form_display.node.event.default.yml)

> [!CAUTION]
> This config is REQUIRED. Without it, the Event Type dropdown (`#edit-field-event-type`) will not appear on the Event creation form, causing Test 3 to fail. This config must be imported AFTER enabling `social_event_type` and `social_event_managers`.

**Step 150** — Enable Event Managers sub-module and its field
- `ddev drush en social_event_managers -y`
- Config: [field.field.node.event.field_event_managers.yml](file:///Users/andreangelantoni/Sites/pl-opensocial/config/sync/field.field.node.event.field_event_managers.yml)

> [!IMPORTANT]
> Same as Step 140: enable the module first, then import config.

**Step 160** — Event attachments: 15 MB limit, expanded extensions
- Config: [field.field.node.event.field_files.yml](file:///Users/andreangelantoni/Sites/pl-opensocial/config/sync/field.field.node.event.field_files.yml)

**Step 170** — Create Event Type taxonomy terms
- Vocabulary: `event_types` (**plural**) — [taxonomy.vocabulary.event_type.yml](file:///Users/andreangelantoni/Sites/pl-opensocial/config/sync/taxonomy.vocabulary.event_type.yml)

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
  $term = \Drupal\taxonomy\Entity\Term::create(["vid" => "event_types", "name" => $name]);
  $term->save();
  echo "Created: $name (tid=" . $term->id() . ")\n";
}
'
```

> [!CAUTION]
> The vocabulary machine name is `event_types` (**plural**), NOT `event_type`. Using the wrong vid will silently create orphaned terms that don't appear in any select widget.
> Terms live in the database, not config YAML. Tids may differ from original (5–11).

> [!CAUTION]
> **taxonomy_access_fix**: Open Social ships with the `taxonomy_access_fix` module, which overrides the default entity reference selection handler. Without the `select terms in event_types` permission, the Event Type dropdown will appear **empty** (no options) even though the terms exist in the database. This MUST be granted after creating terms:
> ```bash
> ddev drush role:perm:add authenticated "select terms in event_types"
> ddev drush role:perm:add administrator "select terms in event_types"
> ```

**Step 180** — Verify event_types terms exist
```bash
ddev drush php:eval 'echo count(\Drupal::entityTypeManager()->getStorage("taxonomy_term")->loadByProperties(["vid" => "event_types"])) . " event_types terms\n";'
```
> Expected: 7 event_types terms

## Stability & Infrastructure (Critical)

**Step 182** — Fix "Unexpected Error" (WSOD) on Event pages
If Event pages crash with `SQLSTATE[42S02]: Table 'db.node__field_event_url' doesn't exist`, manually synchronize the storage definition:
```bash
ddev drush php:eval '\Drupal::entityDefinitionUpdateManager()->installFieldStorageDefinition("field_event_url", "node", "node", \Drupal\field\Entity\FieldStorageConfig::loadByName("node", "field_event_url"));'
```

**Step 184** — Restore Frontend Libraries
The `social_base` theme expects `node-waves` and `autosize` in the libraries folder.
```bash
mkdir -p web/libraries
# If using composer doesn't pull them in, copy from source:
cp -R ~/Sites/pl-opensocial/web/libraries/node-waves web/libraries/
cp -R ~/Sites/pl-opensocial/web/libraries/autosize web/libraries/
```

**Step 186** — Disable problematic HTML filters
The `markdown` filter in `full_html` can cause HTML escaping issues (rendering `<strong>` as plain text).
- Go to `/admin/config/content/formats/manage/full_html`
- Ensure **Markdown** is UNCHECKED.

**Step 188** — Enable Enrollment & Permissions
- `ddev drush en social_event_an_enroll social_event_max_enroll -y`
- Fix permissions (Note: machine names include spaces):
  - `ddev drush role:perm:add authenticated "add event enrollment entities"`
  - `ddev drush role:perm:add authenticated "manage everything enrollments"`
  - `ddev drush role:perm:add authenticated "view published event enrollment entities"`
  - `ddev drush role:perm:add anonymous "add event enrollment entities"`

> Note: `field_event_url` and `field_event_enroll` are OS defaults and require no config changes.

## Page → Wiki Page

**Step 190** — Edit permissions for authenticated users
- Config: [user.role.authenticated.yml](file:///Users/andreangelantoni/Sites/pl-opensocial/config/sync/user.role.authenticated.yml)

**Step 200** — Revision log enabled by default
- Config: [node.type.page.yml](file:///Users/andreangelantoni/Sites/pl-opensocial/config/sync/node.type.page.yml)

**Step 205** — Import the Page form display (places revision log and other fields on the Page form)
- Config: [core.entity_form_display.node.page.default.yml](file:///Users/andreangelantoni/Sites/pl-opensocial/config/sync/core.entity_form_display.node.page.default.yml)

> [!CAUTION]
> This config is REQUIRED. Without it, the revision log field (`#edit-revision-log-0-value`) will not appear on the Page edit form, causing Test 4 to fail.

**Step 210** — Page attachments: 15 MB limit, expanded extensions
- Config: [field.field.node.page.field_files.yml](file:///Users/andreangelantoni/Sites/pl-opensocial/config/sync/field.field.node.page.field_files.yml)

**Step 220** — Clear caches after Phase 2 config imports
- `ddev drush cr`

## Phase 2 Tests

> [!IMPORTANT]
> **Test Environment Setup**:
> 1. **Copy Tests**: `cp -r ~/Sites/pl-opensocial/tests ~/Sites/pl-opensocial-rework/tests`
> 2. **Update Config**: Edit `tests/playwright.config.ts`:
>    - Set `baseURL: 'https://pl-opensocial-rework.ddev.site:8493'`.
>    - Set `timeout: 30000` (Global) and `expect: { timeout: 5000 }` (Assertion). These "fail-fast" timeouts prevent long hangs if elements are missing.
> 3. **Install Dependencies**: Run `npm install` in the `tests/` directory.
> 4. **Install Browsers**: Run `npx playwright install chromium`.

> [!CAUTION]
> **`networkidle` causes permanent hangs**: The test `beforeEach` hook MUST use `waitForLoadState('load')`, NOT `waitForLoadState('networkidle')`. Open Social has perpetual background AJAX (heartbeat/polling) that prevents `networkidle` from ever resolving. This single line causes **every test to hang forever**. If tests appear stuck during login, check this setting first.

> [!IMPORTANT]
> **Test Visibility**: Always use `--reporter=list` or `--reporter=line` when running tests to monitor progress. Do NOT suppress output. If a test appears stuck, check the reporter output for the specific step where it is waiting.

> [!NOTE]
> **Open Social 13 Selector Updates**:
> All test selectors in `phase1-content-types.spec.ts` MUST be scoped to `main` (e.g., `page.locator('main h1')`, `page.locator('main button:has-text("Enroll")')`) to avoid matching hidden admin toolbar elements. Without `main` scoping, selectors like `a[href$="/edit"]` will match toolbar links (e.g., "Edit profile") instead of page content.

**Step 230** — Run (from the `tests/` directory): `npx playwright test e2e/phase1-content-types.spec.ts --reporter=list`

---

**Step 240** — Pre-Phase 3 backup: `ddev export-db --file=backups/phase3-pre.sql.gz`

> [!NOTE]
> `ddev export-db` typically completes in **10-15 seconds**. If it appears stuck, check that DDEV containers are healthy with `docker ps`. Do NOT cancel and retry without checking — the export may be running normally.
> Always create the `backups/` directory first: `mkdir -p backups`

# Phase 3 — Group Structure & Membership

> [!IMPORTANT]
> **Zombie cleanup**: Before starting this phase, run the cleanup script:
> ```bash
> bash ~/Sites/pl-opensocial/scripts/kill-zombies.sh
> ```

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

> [!NOTE]
> Same timing as Step 240 — expect **10-15 seconds**.

# Phase 4 — Content Discovery & Aggregation

> [!IMPORTANT]
> **Zombie cleanup**: Before starting this phase, run the cleanup script:
> ```bash
> bash ~/Sites/pl-opensocial/scripts/kill-zombies.sh
> ```

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
- 14 tests: tags, events calendar/iCal, hot content, promoted content, group RSS.

> [!NOTE]
> **Event Type filter**: The `social_event_type` module renders the "What type of events do you want to see?" dropdown on `/community-events` but does NOT populate it with `event_type` taxonomy terms. The select has only "- Any -". The test (`events.filter.type`) handles this by checking the option count before attempting to select.

> [!WARNING]
> **`ddev drush` intermittent SIGINT**: `ddev drush` commands sometimes receive SIGINT (exit code 130) for no apparent reason. Use `ddev exec drush` as a more reliable alternative. Both execute the same Drush binary inside the container.

---

# How to Reproduce This Site

Starting from a fresh environment:

1. **Phase 1 Base**: Follow Steps 010–040. **Password must be `admin`**.
2. **Infrastructure Fixes**: Follow Steps 182–188 (Storage sync, Libraries, HTML filters, Enrollment).
3. **Custom Code**: Copy all modules from `web/modules/custom/` in the source project.
4. **Configuration**:
   ```bash
   ddev drush cim -y
   # If cim fails due to dependencies, use the php:eval method in Step 100+
   ```
5. **Taxonomy & Metadata**:
   ```bash
   ddev drush php:eval '
   foreach (["Geographical", "Working group", "Distribution", "Event planning", "Archive"] as $name) {
     \Drupal\taxonomy\Entity\Term::create(["vid" => "group_type", "name" => $name])->save();
   }
   foreach (["User group meeting", "Drupalcamp or Regional Summit", "DrupalCon", "Online meeting (e.g. IRC meeting)", "Training (free or commercial)", "Sprint", "Related event (not Drupal-specific)"] as $name) {
     \Drupal\taxonomy\Entity\Term::create(["vid" => "event_types", "name" => $name])->save();
   }
   '
   ddev drush cr
   ```
6. **Verification**: Follow Step 230 to run Phase 1 tests.

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

---

# Phase 5 — Multi-Group Posting

> [!IMPORTANT]
> **Zombie cleanup**: Before starting this phase, run the cleanup script:
> ```bash
> bash ~/Sites/pl-opensocial/scripts/kill-zombies.sh
> ```

**Goal**: Allow a single Topic or Event to appear in multiple groups simultaneously — the defining feature of g.d.o's posting model. Uses the Group module's native `group_relationship` system rather than a separate entity reference field.

## Pre-Phase Backup

**Step 700** — Pre-Phase backup
```bash
mkdir -p backups
ddev export-db --file=backups/phase5-pre.sql.gz
```

## Configuration Changes

**Step 710** — Copy and import config files for unlimited group relationships per node:

```bash
cp ~/Sites/pl-opensocial/config/sync/group.content_type.flexible_group-group_node-topic.yml config/sync/
cp ~/Sites/pl-opensocial/config/sync/group.content_type.flexible_group-group_node-event.yml config/sync/
```

Both set `entity_cardinality: 0` (unlimited groups per node), changed from `1`.

- Config: [group.content_type.flexible_group-group_node-topic.yml](file:///Users/andreangelantoni/Sites/pl-opensocial/config/sync/group.content_type.flexible_group-group_node-topic.yml)
- Config: [group.content_type.flexible_group-group_node-event.yml](file:///Users/andreangelantoni/Sites/pl-opensocial/config/sync/group.content_type.flexible_group-group_node-event.yml)

> [!CAUTION]
> **Breaking change**: `entity_cardinality: 0` allows unlimited group relationships per node. Any downstream code that assumes a 1:1 node-to-group relationship may need review. The existing `group_topics` and `group_events` Views already have `distinct: true`, so no duplicate entries will appear in group streams.

**Step 720** — Import the updated configs
```php
ddev drush php:eval '
$configs = [
  "group.content_type.flexible_group-group_node-topic",
  "group.content_type.flexible_group-group_node-event",
];
foreach ($configs as $name) {
  $yaml = file_get_contents("/var/www/html/config/sync/$name.yml");
  $data = \Drupal\Component\Serialization\Yaml::decode($yaml);
  \Drupal::configFactory()->getEditable($name)->setData($data)->save();
  echo "Imported: $name\n";
}
'
```

## Custom Module: `pl_multigroup`

**Step 730** — Copy module to `web/modules/custom/pl_multigroup/`
- `cp -r ~/Sites/pl-opensocial/web/modules/custom/pl_multigroup web/modules/custom/`
- Contents: `pl_multigroup.info.yml`, `pl_multigroup.module`, `pl_multigroup.libraries.yml`, `css/pl_multigroup.css`

**Step 740** — Enable: `ddev drush en pl_multigroup -y`

> [!IMPORTANT]
> After enabling the module, run `ddev restart` to flush the PHP opcode cache so the web process can find the new classes. A `ddev drush cr` alone may not be sufficient.

Hooks implemented:
- `hook_form_node_form_alter()` — Adds "Group Audience" collapsible fieldset with checkboxes to Topic and Event create/edit forms. Only shows groups the current user is a member of. Pre-checks groups the node already belongs to.
- Custom submit handler `pl_multigroup_node_form_submit()` — Runs **after** the node save handler; creates/removes `group_relationship` entries to match the selected group checkboxes.
- `hook_preprocess_node()` — On **full view**: displays "Posted in: Group A, Group B" with linked group names. On **teasers**: displays "Cross-posted from Group X" badge when viewing from a secondary group.
- `hook_page_attachments()` — Attaches the CSS library.

> [!CAUTION]
> **`drupal_static` timing bug (FIXED 2026-03-13)**: The original module used `drupal_static('pl_multigroup_selected_groups')` to pass group selections from the form submit handler to `hook_node_insert()`/`hook_node_update()`. **This does NOT work** because `hook_node_insert` fires during node save, BEFORE the custom submit handler runs. The submit handler is appended via `$form['actions']['submit']['#submit'][]`, so it executes AFTER the default node save handler.
>
> **The fix**: Call `_pl_multigroup_sync_group_relationships()` directly in the submit handler (which runs post-save and has access to both the saved node and the form values). The `hook_node_insert`/`hook_node_update` hooks were removed entirely. Programmatic node saves (without the form) will not trigger group audience changes — this is intentional.

## Group Stream Behaviour

**Step 750** — No View modifications needed. The existing `group_topics` and `group_events` Views query `group_relationship_field_data` by `gid` (contextual argument). When a node has multiple `group_relationship` entries (one per group), it automatically appears in each group's stream. The `distinct: true` setting on these Views prevents duplicate entries.

## Cache Clear

**Step 755** — Clear caches after Phase 5 config imports and module enable
```bash
ddev drush cr
```

## Phase 5 Tests

> [!IMPORTANT]
> **Test Setup**: Copy updated tests to the rework directory:
> ```bash
> cp ~/Sites/pl-opensocial/tests/e2e/phase4-multigroup.spec.ts ~/Sites/pl-opensocial-rework/tests/e2e/
> ```

**Step 760** — Run (from the `tests/` directory): `./node_modules/.bin/playwright test e2e/phase4-multigroup.spec.ts --reporter=list`
- 7 tests: group audience fieldset, cross-posting, duplicates, cross-post badge, "Posted in" display, non-member exclusion, event cross-posting.
- **All 7 tests pass** (executed 2026-03-13, ~1.1 minutes total).

> [!NOTE]
> **Group Audience fieldset**: The fieldset only appears if the current user is a member of at least one group. The test creates groups as admin first, which automatically makes admin a member. Non-member users will not see the fieldset.
>
> **Cross-posted badge**: The "Cross-posted from" badge appears on teasers rendered via the `group_topics` or `group_events` Views, which render `group_content` entities in `teaser` mode. If Open Social uses a different view mode for the group stream, the badge may render differently.

> [!CAUTION]
> **Checkbox label interception**: Open Social's theme renders `<label>` elements that overlay `<input type="checkbox">` in form elements. Playwright's default click fails because the label intercepts pointer events. All checkbox clicks in the multigroup audience fieldset must use `{ force: true }` to bypass the interception check.
>
> **Duplicate count locator**: The "no duplicates" test uses `main h4 a:has-text("title")` to count topic card headings. Using a broader selector like `main a:has-text()` will match "Read more about …" secondary links and fail with a count of 3 instead of 1.

