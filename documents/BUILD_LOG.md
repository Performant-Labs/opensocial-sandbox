# Build Log — pl-opensocial

This log records every change made to the Open Social site across all implementation phases.
All configuration referenced below is exported to `config/sync/` and can be reproduced with `ddev drush cim -y`.
Custom module code lives in `web/modules/custom/`.

Step numbering uses BASIC-style sparse numbering: Phase 1 = 100s, Phase 2 = 200s, Phase 3 = 300s.
Steps increment by 10 to allow inserting new steps without renumbering.

---

# Phase 1 — Content Types & Text Formats

**Goal**: Configure Topic, Event, and Page content types to match g.d.o's feature set.

> All fields listed below are **Open Social defaults** — no new fields were created.
> Phase 1 work consisted entirely of reconfiguring existing fields and text formats.

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

## Event Enhancements

**Step 140** — Enable Event Type sub-module and its field
- `ddev drush en social_event_type -y`
- Config: [field.field.node.event.field_event_type.yml](file:///Users/andreangelantoni/Sites/pl-opensocial/config/sync/field.field.node.event.field_event_type.yml)

**Step 150** — Enable Event Managers sub-module and its field
- `ddev drush en social_event_managers -y`
- Config: [field.field.node.event.field_event_managers.yml](file:///Users/andreangelantoni/Sites/pl-opensocial/config/sync/field.field.node.event.field_event_managers.yml)

**Step 160** — Event attachments: 15 MB limit, expanded extensions
- Config: [field.field.node.event.field_files.yml](file:///Users/andreangelantoni/Sites/pl-opensocial/config/sync/field.field.node.event.field_files.yml)

**Step 170** — Create Event Type taxonomy terms
- Vocabulary: `event_type` — [taxonomy.vocabulary.event_type.yml](file:///Users/andreangelantoni/Sites/pl-opensocial/config/sync/taxonomy.vocabulary.event_type.yml)
```bash
ddev drush term:create event_type "User group meeting"
ddev drush term:create event_type "Drupalcamp or Regional Summit"
ddev drush term:create event_type "DrupalCon"
ddev drush term:create event_type "Online meeting (e.g. IRC meeting)"
ddev drush term:create event_type "Training (free or commercial)"
ddev drush term:create event_type "Sprint"
ddev drush term:create event_type "Related event (not Drupal-specific)"
```
> Terms live in the database, not config YAML. Tids may differ from original (5–11).

> Note: `field_event_url` and `field_event_enroll` are OS defaults and require no config changes.

## Page → Wiki Page

**Step 180** — Edit permissions for authenticated users
- Config: [user.role.authenticated.yml](file:///Users/andreangelantoni/Sites/pl-opensocial/config/sync/user.role.authenticated.yml)

**Step 190** — Revision log enabled by default
- Config: [node.type.page.yml](file:///Users/andreangelantoni/Sites/pl-opensocial/config/sync/node.type.page.yml)

**Step 200** — Page attachments: 15 MB limit, expanded extensions
- Config: [field.field.node.page.field_files.yml](file:///Users/andreangelantoni/Sites/pl-opensocial/config/sync/field.field.node.page.field_files.yml)

**Step 210** — Clear caches after Phase 1 config imports
- `ddev drush cr`

## Phase 1 Tests

**Step 220** — Run: `npx playwright test e2e/phase1-content-types.spec.ts`

---

# Phase 2 — Group Structure & Membership

**Goal**: Configure Open Social's `flexible_group` to replicate g.d.o's group types, membership models, archive enforcement, moderation queue, and submission guidelines.

## Group Type Taxonomy Terms

Vocabulary: `group_type` (Open Social default) — [taxonomy.vocabulary.group_type.yml](file:///Users/andreangelantoni/Sites/pl-opensocial/config/sync/taxonomy.vocabulary.group_type.yml)

**Step 300** — `ddev drush term:create group_type "Geographical" --description="Local user groups by city/region"`

**Step 310** — `ddev drush term:create group_type "Working group" --description="Module, feature, or initiative coordination"`

**Step 320** — `ddev drush term:create group_type "Distribution" --description="Drupal distribution projects"`

**Step 330** — `ddev drush term:create group_type "Event planning" --description="DrupalCon and camp organising"`

**Step 340** — `ddev drush term:create group_type "Archive" --description="Inactive groups (read-only)"`

> Terms live in the database, not config YAML. Tids may differ from original (12–16).

## Membership Models

**Step 350** — No configuration changes needed. Open Social's `flexible_group` already supports all g.d.o models:

| g.d.o model | `field_group_allowed_join_method` | `field_flexible_group_visibility` |
|---|---|---|
| Open | `direct` | `public` |
| Moderated | `request` | `public` or `community` |
| Invite only | `invite` | `secret` |
| Closed | `invite` | `secret` |

## Group Directory

**Step 360** — Modify `newest_groups` view: filter out secret groups by default
- Config: [views.view.newest_groups.yml](file:///Users/andreangelantoni/Sites/pl-opensocial/config/sync/views.view.newest_groups.yml)

## Custom Module: `pl_group_extras`

**Step 370** — Copy module to `web/modules/custom/pl_group_extras/`
- `cp -r ~/Sites/pl-opensocial/web/modules/custom/pl_group_extras web/modules/custom/`
- Contents: `pl_group_extras.info.yml`, `pl_group_extras.module`, `pl_group_extras.libraries.yml`, `css/pl_group_extras.css`

**Step 380** — Enable: `ddev drush en pl_group_extras -y`

Hooks implemented:
- `hook_form_alter()` — blocks content creation in archived groups; injects submission guidelines
- `hook_entity_presave()` — sets non-admin groups to unpublished (moderation queue)
- `hook_entity_insert()` — sends email notification for pending groups
- `hook_preprocess_group()` — adds "Archived" badge
- `hook_node_access()` — denies content creation in archived groups

## Pending Groups View

**Step 390** — Import `pending_groups` view at `/admin/groups/pending`
- Config: [views.view.pending_groups.yml](file:///Users/andreangelantoni/Sites/pl-opensocial/config/sync/views.view.pending_groups.yml)
- Note: the `operations` field was removed after initial deployment due to a broken `entity_operations` plugin. The exported YAML already has this fix.

**Step 395** — Clear caches after Phase 2 config imports
- `ddev drush cr`

## Phase 2 Tests

**Step 400** — Run: `npx playwright test e2e/phase2-groups.spec.ts`
- 13 tests: group creation, directory filtering, archiving, moderation queue, guidelines.

---

# Phase 3 — Content Discovery & Aggregation

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

**Step 620** — Clear caches after Phase 3 config imports
- `ddev drush cr`

## Phase 3 Tests

**Step 630** — Run: `npx playwright test e2e/phase3-discovery.spec.ts`

---

# How to Reproduce This Site

Starting from a vanilla Open Social 13.0.0 installation:

```bash
# 1. Import all configuration
ddev drush cim -y

# 2. Enable custom modules (creates DB tables, registers hooks)
ddev drush en pl_opensocial_wiki pl_group_extras pl_discovery -y

# 3. Create taxonomy terms (not stored in config)
ddev drush term:create group_type "Geographical"
ddev drush term:create group_type "Working group"
ddev drush term:create group_type "Distribution"
ddev drush term:create group_type "Event planning"
ddev drush term:create group_type "Archive"
ddev drush term:create event_type "User group meeting"
ddev drush term:create event_type "Drupalcamp or Regional Summit"
ddev drush term:create event_type "DrupalCon"
ddev drush term:create event_type "Online meeting (e.g. IRC meeting)"
ddev drush term:create event_type "Training (free or commercial)"
ddev drush term:create event_type "Sprint"
ddev drush term:create event_type "Related event (not Drupal-specific)"

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
