# Phase 7 — Content Moderation & Group Admin

Implements the final phase of the IMPLEMENTATION_PLAN: content pinning within groups, group organizer sidebar, group-level language override, and group mission statement sidebar block.

**Decisions confirmed**:
- **Languages**: 12 new languages (g.d.o set). Translations auto-downloaded by `locale` module.
- **Pinning**: Global flag (not per-group). Multiple posts can be pinned simultaneously, so global scope is acceptable.
- **Organizer sidebar**: Move existing `group_managers` block to sidebar (`complementary_bottom`), visible on all `/group/*` pages.

> [!IMPORTANT]
> **Numbering**: BUILD_LOG steps use BASIC-style sparse numbering. Phase 7 steps continue from **Step 1000**.

---

## User Review Required

> [!IMPORTANT]
> **Group-level language (7.3)** is the most complex item. It requires enabling `locale` + `content_translation` modules, adding translations for 14 languages, creating a custom language negotiation plugin, and adding a `field_group_language` field to the group entity. This is significantly more involved than pin/sidebar/mission and carries the highest risk of unexpected issues.

> [!WARNING]
> **Language negotiation plugin** requires a custom Drupal service (`LanguageNegotiationGroup`) that checks group context from the route. If Open Social's group routing differs from core expectations, the plugin may not detect the group context correctly. This will need careful testing.

---

## Existing Infrastructure (No Work Needed)

The following already exist in `config/sync/`:

| Item | Status | Config File |
|---|---|---|
| `promote_homepage` flag | ✅ Deployed (Phase 4) | `flag.flag.promote_homepage.yml` |
| `group_managers` View + block | ✅ Exists (OS default) | `views.view.group_managers.yml`, `block.block.views_block__group_managers_block_list_managers.yml` |
| `field_group_description` | ✅ Exists (OS default) | `field.field.group.flexible_group.field_group_description.yml` |
| `language` core module | ✅ Enabled | `core.extension.yml` |

---

## Proposed Changes

### 7.1 — Pin Content in Group

A custom module `pl_group_pin` that uses the Flag module to let group managers pin/unpin topics in the group stream.

#### [NEW] `web/modules/custom/pl_group_pin/pl_group_pin.info.yml`
Module definition. Dependencies: `flag`, `group`, `node`.

#### [NEW] `web/modules/custom/pl_group_pin/pl_group_pin.module`
Hooks:
- `hook_preprocess_node()` — Adds "Pinned" badge markup + CSS class to pinned nodes in group stream.
- `hook_views_query_alter()` — Alters `group_topics` view to sort pinned content above chronological entries (LEFT JOIN on `flagging` table, ORDER BY `flagging.id DESC`).

#### [NEW] `config/sync/flag.flag.pin_in_group.yml`
Flag definition:
- `entity_type: node`, `bundles: [topic, event, page]`
- `global: false` (per-user flagging, but manager-only via permissions)
- `flag_short: "Pin in group"`, `unflag_short: "Unpin"`
- `show_as_field: true`, `show_on_form: false`

#### [MODIFY] `config/sync/user.role.contentmanager.yml`
#### [MODIFY] `config/sync/user.role.sitemanager.yml`
Grant `flag pin_in_group` and `unflag pin_in_group` permissions.

> [!NOTE]
> **Pin scope**: The Flag module creates a single flagging per user+entity pair. Pinning is global — if a topic is cross-posted to Groups A and B, pinning it pins it in both. This is acceptable because **multiple posts can be pinned simultaneously** (each node gets its own flagging record). There is no "only one pin" constraint.

---

### 7.2 — Group Organizer Sidebar

The `group_managers` view and block **already exist** in config. The block is currently placed in the `content` region and restricted to `/group/*/about`.

#### [MODIFY] `config/sync/block.block.views_block__group_managers_block_list_managers.yml`
- Change `region: content` → `region: complementary_bottom` (sidebar, below action buttons)
- Change `pages: '/group/*/about'` → `pages: '/group/*'` (visible on **all** group pages)

No new code needed — config-only. The block already has `block_hide_empty: true` so it won't render on non-group pages.

---

### 7.3 — Group-Level Language

#### Enable required modules

```bash
ddev drush en locale content_translation language_negotiation -y
```

> [!CAUTION]
> `locale` is a heavy module — it downloads translation files for all enabled modules. First enable may take 30-60 seconds and trigger batch operations. Do NOT cancel.

#### Add languages

```bash
ddev drush php:eval '
$languages = ["pt-br", "ca", "zh-hans", "da", "nl", "fr", "de", "nb", "ru", "es", "sv", "tr"];
$manager = \Drupal::entityTypeManager()->getStorage("configurable_language");
foreach ($languages as $langcode) {
  if (!$manager->load($langcode)) {
    $lang = $manager->create(["id" => $langcode]);
    $lang->save();
    echo "Added: $langcode\n";
  }
}
'
```

#### [NEW] `config/sync/field.storage.group.field_group_language.yml`
Field storage: `type: language`, `cardinality: 1`.

#### [NEW] `config/sync/field.field.group.flexible_group.field_group_language.yml`
Field instance on `flexible_group`. Label: "Group language". Default: `und` (Language neutral).

#### [MODIFY] `config/sync/core.entity_form_display.group.flexible_group.default.yml`
Add `field_group_language` to the group form with `language_select` widget.

#### [NEW] `web/modules/custom/pl_group_language/pl_group_language.info.yml`
Module definition. Dependencies: `language`, `group`.

#### [NEW] `web/modules/custom/pl_group_language/pl_group_language.services.yml`
Register language negotiation plugin service.

#### [NEW] `web/modules/custom/pl_group_language/src/Plugin/LanguageNegotiation/LanguageNegotiationGroup.php`
Custom `LanguageNegotiationMethodInterface` plugin:
- Reads group from route parameter (`\Drupal::routeMatch()->getParameter('group')`)
- If group has `field_group_language` set (not `und`), returns that langcode
- Otherwise returns `FALSE` to fall through to next negotiation method
- Must respect user locale override (plugin weight below user preference)

#### [MODIFY] `config/sync/language.types.yml`
Add `language-group` negotiation method to the `language_interface` type.

> [!WARNING]
> **User override**: The IMPLEMENTATION_PLAN says "User with personal locale set to Spanish sees Spanish UI even inside a French-language group." This means the user's language settings must have **higher priority** than the group language plugin. We must configure the negotiation order: User → Group → URL → Default.

---

### 7.4 — Group Mission Statement Sidebar Block

#### [NEW] `web/modules/custom/pl_group_mission/pl_group_mission.info.yml`
Module definition. Dependencies: `block`, `group`.

#### [NEW] `web/modules/custom/pl_group_mission/src/Plugin/Block/GroupMissionBlock.php`
Custom block plugin:
- Reads group from route context (`@group.group_route_context:group`)
- Renders `field_group_description` value (truncated to ~300 chars with "Read more" link to About tab)
- Returns empty render array if no description set

#### Block placement (via drush, same pattern as Phase 7/Step 925):
```bash
ddev drush php:eval '...'  # Place block in complementary_bottom, visibility: /group/*
```

---

### 7.5 — Phase 7 Tests

#### [NEW] `tests/e2e/phase7-moderation.spec.ts`

Tests to write (matching IMPLEMENTATION_PLAN test IDs):

| Test ID | What it verifies |
|---|---|
| `pin.manager.can.pin` | Group Manager pins a Topic; it appears above newer Topics in the stream |
| `pin.member.cannot.pin` | Regular member does not see the pin option |
| `pin.badge` | Pinned Topic displays a "Pinned" visual indicator |
| `pin.unpin` | Manager unpins a Topic; it returns to chronological position |
| `homepage.promote` | Editor promotes content to homepage; block on front page shows the item |
| `organizer.sidebar` | Group Manager's username and avatar appear in the group sidebar |
| `group.language.fr` | Group language set to French; group homepage UI labels render in French |
| `group.language.user.override` | User with personal locale Spanish sees Spanish UI even inside French-language group |
| `about.mission` | Group Mission Statement is visible on the group homepage sidebar |

---

## BUILD_LOG Steps (to be appended)

```
Step 1000 — Pre-Phase backup
Step 1010 — Copy + enable pl_group_pin module
Step 1020 — Import pin_in_group flag config
Step 1030 — Grant pin permissions to contentmanager + sitemanager
Step 1040 — Update group_managers block placement (sidebar, expanded visibility)
Step 1050 — Enable locale + content_translation modules
Step 1060 — Add 12 languages via drush
Step 1070 — Add field_group_language field storage + instance
Step 1080 — Update group form display with language selector
Step 1090 — Copy + enable pl_group_language module
Step 1100 — Configure language negotiation order
Step 1110 — Copy + enable pl_group_mission module
Step 1120 — Place mission block in sidebar
Step 1130 — Clear caches: ddev drush cr
Step 1140 — Run phase7-moderation.spec.ts tests
```

---

## Verification Plan

### Automated Tests

Run from the `tests/` directory:

```bash
./node_modules/.bin/playwright test e2e/phase7-moderation.spec.ts --reporter=list
```

All 9 tests must pass. Expected runtime: ~1-2 minutes.

> [!IMPORTANT]
> **Test conventions** (from BUILD_LOG):
> - Use `waitForLoadState('load')`, **never** `networkidle`
> - Scope selectors to `main` to avoid admin toolbar matches
> - Use `{ force: true }` for checkbox clicks (social theme label overlay)
> - Use `--reporter=list` for progress visibility
> - Global timeout: 30000ms, assertion timeout: 5000ms

### Manual Verification

After all automated tests pass:
1. Visit a group page → confirm "Group managers" block appears in the **sidebar** (not main content area)
2. Set a group's language to French → visit group stream → confirm French UI labels
3. Pin a topic in a group → confirm "Pinned" badge and sort order
4. Check group homepage sidebar → confirm mission statement summary block appears
