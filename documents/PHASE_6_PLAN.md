# Phase 6 Plan — User Profiles & History Stats

**Objective**: Populate user profiles with fields matching g.d.o (organization, country, expertise), add a contribution stats block, and ensure profile completeness guidance.

**Build log numbering**: Steps 900–949

---

## Existing Infrastructure

Open Social already has a **complete profile system** — 17 profile fields and 14 view modes:

### Profile Fields (already defined on `profile` entity)

| Field | Type | Status |
|---|---|---|
| `field_profile_first_name` | string | Defined, unpopulated |
| `field_profile_last_name` | string | Defined, unpopulated |
| `field_profile_image` | image | Defined, unpopulated |
| `field_profile_banner_image` | image | Defined, unpopulated |
| `field_profile_organization` | string | Defined, unpopulated |
| `field_profile_function` | string (job title) | Defined, unpopulated |
| `field_profile_expertise` | entity_reference (taxonomy) | Defined, unpopulated |
| `field_profile_interests` | entity_reference (taxonomy) | Defined, unpopulated |
| `field_profile_self_introduction` | text_long (bio) | Defined, unpopulated |
| `field_profile_summary` | string | Defined, unpopulated |
| `field_profile_address` | address | Defined, unpopulated |
| `field_profile_phone_number` | telephone | Defined, unpopulated |
| `field_profile_profile_tag` | entity_reference | Defined, unpopulated |
| `field_profile_show_email` | boolean | Defined |
| `field_group_affiliation` | group_affiliation | Defined |
| `field_other_affiliations` | entity_reference_revisions | Defined |
| `field_enable_other_affiliations` | boolean | Defined |

### Existing Views

- `activity_stream_profile` — User's activity feed on profile page
- `following_users` — List of people user follows
- `followers_user` — List of user's followers
- `following_tags` — Tags user follows
- `groups` — User's groups

### Profile View Modes

14 modes including: `default`, `hero`, `teaser`, `medium_teaser`, `statistic`, `compact`, `table`, `small`, etc.

### g.d.o Profile Fields Mapping

| g.d.o Feature | Open Social Equivalent | Action Needed |
|---|---|---|
| Full name | `field_profile_first_name/last_name` | Already exists |
| Avatar | `field_profile_image` | Already exists |
| Banner | `field_profile_banner_image` | Already exists |
| Organization | `field_profile_organization` | Already exists |
| Bio/About | `field_profile_self_introduction` | Already exists |
| Location/Country | `field_profile_address` | Already exists |
| Expertise | `field_profile_expertise` | Already exists |
| Interests | `field_profile_interests` | Already exists |
| Social links (website, Twitter, LinkedIn) | **Not present** | Add new fields |
| Languages spoken | **Not present** | Add new field |
| Contribution stats (topics, comments, groups) | **Not present** | Add custom block |
| Profile completeness indicator | **Not present** | Add custom block |

---

## What's Missing (custom work required)

### 1. New profile fields — Social links & languages
- `field_profile_website` (link)
- `field_profile_social_links` (link, multiple values — for Twitter/LinkedIn/etc.)
- `field_profile_languages` (string, or entity_reference to a taxonomy)

### 2. Contribution stats block
Custom block plugin showing:
- Topics created count
- Comments posted count  
- Events organized count
- Groups joined count
- Days since account creation

### 3. Profile completeness indicator
Custom block showing percentage of fields filled and prompting users to complete their profile.

---

## Proposed Steps

### 6.1 Add missing profile fields

**Step 900** — Add social links field:
```bash
ddev drush php:eval '...'  # Create field storage + instance for field_profile_website and field_profile_social_links
```
Or via config sync YAML files.

**Step 905** — Add languages field:
```bash
ddev drush php:eval '...'  # Create field_profile_languages
```

**Step 910** — Enable fields on form display and view display for the `default` and `hero` view modes.

---

### 6.2 Custom module: `pl_profile_stats`

**Step 915** — Create `web/modules/custom/pl_profile_stats/` with:
- `pl_profile_stats.info.yml`
- `pl_profile_stats.module` — `hook_preprocess_profile()` to inject stats variables
- `src/Plugin/Block/ContributionStatsBlock.php` — Block plugin counting topics, comments, events, groups
- `src/Plugin/Block/ProfileCompletenessBlock.php` — Block plugin computing field fill percentage

**Step 920** — Enable: `ddev drush en pl_profile_stats -y`

**Step 925** — Place blocks in the profile page region:
```bash
ddev drush php:eval '...'  # Create block placements
```

---

### 6.3 Populate admin profile with sample data

**Step 930** — Set sample profile data for admin (uid 1) for testing:
```bash
ddev drush php:eval '
$profile = \Drupal::entityTypeManager()->getStorage("profile")->loadByProperties(["uid" => 1, "type" => "profile"]);
$profile = reset($profile);
$profile->set("field_profile_first_name", "Site");
$profile->set("field_profile_last_name", "Administrator");
$profile->set("field_profile_organization", "Open Social Foundation");
$profile->set("field_profile_function", "Platform Admin");
$profile->set("field_profile_self_introduction", "Managing the Open Social platform.");
$profile->save();
'
```

---

### 6.4 Cache clear and config export

**Step 940** — Clear caches and export:
```bash
ddev drush cr
ddev drush cex -y
```

---

## Verification Plan

### Automated Tests — `tests/e2e/phase6-profiles.spec.ts`

| Test ID | Description |
|---|---|
| `profile.page.loads` | Admin profile page loads at `/user/1/home` without errors |
| `profile.fields.display` | First name, last name, organization display on profile |
| `profile.edit.works` | Admin can edit their own profile via `/profile/{id}/edit` |
| `profile.stats.block` | Contribution stats block shows counts (topics, comments, groups) |
| `profile.completeness.block` | Profile completeness block shows percentage |
| `profile.social.links` | Social links field accepts and displays URLs |
| `profile.image.upload` | Profile image can be uploaded and displays |
| `profile.other.user` | A second user's profile page renders properly |

**Run**: `./node_modules/.bin/playwright test e2e/phase6-profiles.spec.ts --reporter=list` (from `tests/` directory)

---

## Complexity Assessment

**Medium**. Most profile fields already exist and just need populating/enabling. Custom work:
- 3 new fields (drush/config)
- 2 custom block plugins (stats + completeness)
- 8 Playwright tests

Estimated effort: ~30 minutes.
