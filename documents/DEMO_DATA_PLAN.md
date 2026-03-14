# Demo Data Plan

Generate realistic content that exercises every feature on the platform.
All data is created via `ddev drush php:eval` for reproducibility.

> Items marked **⚙** require custom module features.

---

## Log Protocol

Run **before every phase**:
```bash
ddev exec bash -c 'truncate -s 0 /tmp/logpipe'
ddev drush watchdog:delete all -y
```

Run **after every phase**:
```bash
ddev exec bash -c 'cat /tmp/logpipe | grep -i "error\|fatal\|warning" | tail -20'
ddev drush watchdog:show --severity=error --count=10
ddev drush watchdog:show --severity=warning --count=10
```

---

# Data Reference

### Users (7 + admin)

| Username | Roles | Profile | Photo | Purpose |
|----------|-------|---------|-------|---------|
| `admin` (uid 1) | Administrator | Complete | Yes | Full-access demo |
| `maria_chen` | Content Manager | Complete | Yes | Promotes content, moderates |
| `james_okafor` | Site Manager | Complete | Yes | Approves groups, site config |
| `elena_garcia` | Authenticated | Complete | Yes | Active contributor |
| `ravi_patel` | Authenticated | Partial (no bio) | **No** | ⚙ Profile completeness |
| `sophie_mueller` | Authenticated | Complete, lang: DE | Yes | User language override |
| `alex_novak` | Authenticated | Minimal (name only) | **No** | Low-activity user |

All passwords: `demo123`

**Profile photos:** Generated via `generate_image` as realistic headshot portraits. Saved to `tests/fixtures/photos/` and uploaded to each user's profile. ravi_patel and alex_novak intentionally have no photo (to demonstrate ⚙ profile completeness).

### Groups (7)

| Group | Visibility | Group Type | Lang | Purpose |
|-------|-----------|------------|------|---------|
| DrupalCon Portland 2026 | Open | Event Planning | en | Events, multi-group |
| Drupal France | Open | Geographical | **fr** | ⚙ Group language |
| Core Committers | **Closed** | Working Group | en | Approval flow |
| Thunder Distribution | Open | Distribution | en | Tags, cross-posting |
| Leadership Council | **Secret** | Working Group | en | Hidden from directory |
| Camp Organizers EMEA | Open | Event Planning | en | RSS, iCal |
| Legacy Infrastructure | Open → **Archive** | Archive | en | ⚙ Archive badge |

### Membership Matrix

| User | Portland | France | Core | Thunder | Council | EMEA | Legacy |
|------|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| admin | GM | ✓ | GM | ✓ | GM | ✓ | ✓ |
| maria_chen | ✓ | ✓ | ✓ | GM | | GM | ✓ |
| james_okafor | ✓ | | GM | ✓ | ✓ | ✓ | |
| elena_garcia | ✓ | ✓ | *pending* | ✓ | | ✓ | ✓ |
| ravi_patel | ✓ | | | ✓ | | | |
| sophie_mueller | | ✓ | | | | ✓ | |
| alex_novak | ✓ | | | | | | |

*GM = Group Manager · ✓ = Member*

### Topics (12)

| Title | Group(s) | Author | Tags | Feature |
|-------|----------|--------|------|---------|
| Sprint Planning: Portland 2026 | Portland | admin | sprint, drupalcon | ⚙ Pinned |
| Venue Logistics Update | Portland | maria | logistics | 3 comments |
| Nouveau thème pour drupal.fr | France | elena | theme, frontend | French content |
| Patch Review Process RFC | Core | james | process, core | 4 comments |
| Thunder 7.0 Roadmap | Thunder, Portland | maria | roadmap, thunder | ⚙ Cross-post |
| Getting Started with Paragraphs | Thunder | elena | paragraphs, tutorial | Promoted |
| Camp Barcelona Recap | EMEA | elena | drupalcamp, recap | Attachment |
| Budget Proposal Q3 | Council | admin | budget | Secret group |
| Migration Guide: D9 → D10 | Legacy | maria | migration, d10 | Archive test |
| Welcome to Open Social | *(none)* | admin | welcome | ⚙ Wiki link |
| Community Code of Conduct | *(none)* | admin | community, policy | Promoted |
| Weekly Standup Notes | Portland, EMEA | james | standup | ⚙ Cross-post |

### Events (5)

| Title | Group(s) | Author | Type | Date | Max |
|-------|----------|--------|------|------|-----|
| DrupalCon Portland Keynote | Portland | admin | DrupalCon | +30d | 500 |
| Code Sprint: Migrate API | Portland | james | Sprint | +31d | 30 |
| Réunion mensuelle Paris | France | elena | User Group Meeting | +14d | — |
| Core Committer Sync | Core | james | Other | +7d | — |
| DrupalCamp Barcelona | EMEA, Portland | maria | DrupalCamp | +60d | — |

**Enrollments:** Keynote (elena, ravi, sophie, alex) · Sprint (elena, ravi) · Paris (sophie) · Barcelona (elena, ravi, alex) — **10 total**

### Pages (3)

| Title | Author | Notes |
|-------|--------|-------|
| Getting Started with Open Social | admin | Wiki-link target |
| Contribution Guidelines | maria | 3 revisions |
| Platform FAQ | admin | Reference |

### Comments (8)

| On | By | Text |
|----|-----|------|
| Venue Logistics Update | elena | "Can we get a budget estimate?" |
| Venue Logistics Update | james | "I'll check with the venue" |
| Venue Logistics Update | maria | "Budget approved ✓" |
| Patch Review Process RFC | elena | "What about automated checks?" |
| Patch Review Process RFC | ravi | "+1 on automation" |
| Patch Review Process RFC | james | "Let's add CI requirements" |
| Patch Review Process RFC | admin | "Merged into the RFC. Thanks all!" |
| Thunder 7.0 Roadmap | ravi | "Excited about layout builder" |

### Flags & Follows

| Action | Target | By |
|--------|--------|-----|
| ⚙ Pin in group | Sprint Planning (Portland) | admin |
| ⚙ Promote homepage | Getting Started with Paragraphs | maria |
| ⚙ Promote homepage | Community Code of Conduct | maria |
| Follow content | Patch Review Process RFC | elena |
| Follow content | Thunder 7.0 Roadmap | ravi |
| Follow tag | `core` | elena |
| Follow user | maria_chen | ravi |

---

# Execution Phases

## Phase 1 — Clean Slate

**Clear logs**

1. Delete all nodes, groups, users (uid > 1), flaggings, enrollments (see BUILD_LOG Step 1200)
2. `ddev drush cr`
3. Snapshot empty database:
```bash
ddev export-db --file=backups/demo-empty-$(date +%Y%m%d-%H%M).sql.gz
```

**Check logs** — expect 0 errors

**Assert:**
- Nodes = 0, Groups = 0, Users = 1, Flaggings = 0

---

## Phase 2 — Users & Taxonomy

**Clear logs**

1. Generate 5 profile photos via `generate_image` (realistic headshots for admin, maria, james, elena, sophie)
2. Create 6 user accounts (password: `demo123`)
3. Assign roles: maria → contentmanager, james → sitemanager
4. Populate profiles per the Users table
5. Upload profile photos to 5 users (skip ravi and alex)
6. Leave ravi_patel profile intentionally incomplete (no bio, no photo)
7. Set sophie_mueller preferred language to German
8. Create 20 terms in `social_tagging` vocabulary

**Check logs** — expect 0 errors

**Assert:**
- Users = 7 (uid 1 + 6 new)
- `maria_chen` has role `contentmanager`
- `james_okafor` has role `sitemanager`
- `sophie_mueller` preferred language = `de`
- `ravi_patel` `field_profile_self_introduction` is empty
- `social_tagging` term count = 20

---

## Phase 3 — Groups & Memberships

**Clear logs**

1. Create 7 groups per the Groups table (visibility, group type term, ⚙ mission)
2. Set ⚙ group language on "Drupal France" to French
3. Add members per the Membership Matrix (with GM roles where shown)
4. Leave elena_garcia as *pending* in Core Committers

**Check logs** — expect 0 errors

**Assert:**
- Groups = 7
- "Leadership Council" visibility = `members` (secret)
- "Core Committers" join method = `added` (closed)
- "Drupal France" `field_group_language` = `fr`
- All groups have non-empty `field_group_description`
- Portland member count = 7, Council member count = 3
- admin has GM role in Portland, Core, Council
- elena_garcia status in Core Committers = pending

---

## Phase 4 — Content (Topics, Events, Pages)

**Clear logs**

1. Create 12 topics per the Topics table (correct authors, groups, tags)
2. Cross-post "Thunder 7.0 Roadmap" → Thunder + Portland
3. Cross-post "Weekly Standup Notes" → Portland + EMEA
4. Set wiki link in "Welcome to Open Social": `[[Getting Started with Paragraphs]]`
5. Create a dummy PDF file and attach it to "Camp Barcelona Recap"
6. Create 5 events per the Events table (types, dates, enrollment caps)
7. Cross-post "DrupalCamp Barcelona" → EMEA + Portland
8. Create 3 pages; add 3 revisions to "Contribution Guidelines"

**Check logs** — expect 0 errors

**Assert:**
- Nodes = 20 (12 topics + 5 events + 3 pages)
- "Thunder 7.0 Roadmap" has 2 group_relationship entries
- "Weekly Standup Notes" has 2 group_relationship entries
- "DrupalCamp Barcelona" has 2 group_relationship entries
- "Welcome to Open Social" body contains `[[Getting Started with Paragraphs]]`
- Each event has correct `field_event_type` term reference
- "Contribution Guidelines" revision count = 3
- "Budget Proposal Q3" belongs only to Leadership Council

---

## Phase 5 — Comments & Enrollments

**Clear logs**

1. Add 8 comments per the Comments table (correct authors and threading)
2. Create 10 event enrollments per the Enrollments list

**Check logs** — expect 0 errors

**Assert:**
- Comments = 8
- "Venue Logistics Update" comment count = 3
- "Patch Review Process RFC" comment count = 4
- "Thunder 7.0 Roadmap" comment count = 1
- Event enrollments = 10
- Keynote enrollment count = 4, Sprint = 2

---

## Phase 6 — Flags, Notifications & Archive

**Clear logs**

1. ⚙ Pin "Sprint Planning: Portland 2026" in Portland (admin)
2. ⚙ Promote "Getting Started with Paragraphs" + "Community Code of Conduct" (maria)
3. Follow "Patch Review Process RFC" (elena) and "Thunder 7.0 Roadmap" (ravi)
4. elena follows tag `core`
5. ravi follows user `maria_chen`
6. sophie opts out of all notifications
7. ⚙ Mark "Legacy Infrastructure" as archived

**Check logs** — expect 0 errors

**Assert:**
- `pin_in_group` flagging exists for Sprint Planning, uid = admin
- `promote_homepage` flagging count = 2
- `follow_content` flagging count = 2
- Tag follow flagging exists: elena → `core` term
- User follow flagging exists: ravi → maria
- "Legacy Infrastructure" `field_group_type` = Archive term

---

## Phase 7 — Index, Snapshot & Verify

**Clear logs**

1. `ddev drush search-api:index`
2. `ddev drush cr`
3. Snapshot complete demo database:
```bash
ddev export-db --file=backups/demo-complete-$(date +%Y%m%d-%H%M).sql.gz
```
4. Run visual verification checklist (below)

**Check logs** — expect 0 errors

**Assert:**
- `ddev drush search-api:status` → 0 items remaining
- Search for "Portland" returns results
- Search for "Paragraphs" returns results

---

# Verification Checklist

Browse the site and confirm:

- [ ] Homepage shows promoted content block (2 items)
- [ ] `/hot` ranks content by engagement
- [ ] `/all-groups` shows 6 groups (not Secret, not Archive)
- [ ] `/all-groups` with Archive filter shows Legacy group with ⚙ badge
- [ ] Search (`/search/all`) returns results via Solr
- [ ] Drupal France group renders French UI labels
- [ ] sophie_mueller in France group sees German UI (override)
- [ ] Sprint Planning pinned at top of Portland stream
- [ ] Thunder 7.0 Roadmap appears in both Thunder + Portland streams
- [ ] Weekly Standup shows "Posted in" links to Portland + EMEA
- [ ] DrupalCamp Barcelona shows ⚙ cross-posted badge in Portland
- [ ] Admin profile shows ⚙ contribution stats
- [ ] ravi_patel profile shows ⚙ completeness < 100%
- [ ] `/upcoming-events/ical` returns valid iCal
- [ ] `/group/{emea}/events/ical` returns group-scoped iCal
- [ ] `/group/{rss-group}/stream/feed` returns valid RSS XML with content items
- [ ] Legacy Infrastructure blocks new topic creation
- [ ] Core Committers shows elena_garcia as pending
- [ ] Leadership Council hidden from non-members on `/all-groups`
- [ ] elena_garcia notification settings show followed content
- [ ] Tags aggregation view shows tagged content
- [ ] Camp Barcelona Recap has PDF file attachment visible
- [ ] Users with photos show headshot on profile; ravi/alex show default avatar
