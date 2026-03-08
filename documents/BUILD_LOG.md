# Phase 1: Content Types & Text Formats - Build Log

## Overview
Implemented and verified the core content types and text formats for the Open Social migration.

## Changes
### 1.1 Topic → Discussion
- Configured Topic body field to support Full HTML and Markdown.
- Enabled `[[title]]` wiki-link filter via Linkit.
- Enabled summary/teaser toggle.
- Configured file attachments (15MB limit, expanded extensions).

### 1.2 Event Enhancements
- Added Event URL link field.
- Created Event Type taxonomy and terms (Meeting, Webinar, Conference, Workshop, DrupalCon).
- Added Event Type field to Event content type.
- Added Organizers user reference field.
- Added file attachments.
- Enabled enrollment by default.

### 1.3 Page → Wiki Page
- Configured Page edit permissions for authenticated users.
- Added file attachments.
- Enabled revision log.

## Verification
- **Playwright Test Suite**: `tests/e2e/phase1-content-types.spec.ts`
  - verified Topic creation and Markdown rendering.
  - verified Event creation with metadata.
  - verified Page edit permissions.
- **Manual Verification**: Confirmed HTML tag preservation in `full_html` format by disabling redundant display filters.

## Value Provided
Phase 1 transforms the standard Open Social installation into a specialized platform for Group coordination, matching the core functionality of groups.drupal.org:
- **Collaborative Writing**: Any logged-in user can now contribute to Wiki pages, with full revision tracking for accountability.
- **Enhanced Discussion**: Support for Markdown and `[[Title]]` wiki-links enables technical users to write rich content with ease.
- **Structured Events**: New metadata (Event Types, URLs, and Organizers) improves event discovery and management.
- **Robust Media**: Increased attachment limits (15MB) and support for a wide range of file types (including `.patch` and `.diff`) cater to the Drupal developer community.

## Status: COMPLETE

---

# Phase 2: Group Structure & Membership Models - Build Log

## Overview
Configure group types, membership models, group directory, archive mechanism, moderation queue, and submission guidelines.

## Pre-requisite
- Database backed up to `backups/phase2-pre.sql.gz`

## Changes

### 2.1 Group Types
- Created taxonomy terms in `group_type` vocabulary:
  - Geographical (tid:12), Working group (tid:13), Distribution (tid:14), Event planning (tid:15), Archive (tid:16)

### 2.2 Membership Models
- Open Social's `flexible_group` already supports Direct/Request/Invite join methods — mapped directly to g.d.o's Open/Moderated/Invite-only/Closed models.

### 2.3 Group Directory
- Existing `newest_groups` view at `/all-groups` has Group Type exposed filter and teaser display.

### 2.4 Archive Group Type
- Created custom module `pl_group_extras` (`web/modules/custom/pl_group_extras/`):
  - `hook_form_alter()` blocks content creation in Archive-type groups
  - `hook_node_access()` denies create permission for Archive groups
  - `hook_preprocess_group()` renders "Archived" badge via CSS class `.group-badge--archived`

### 2.5 Group Moderation Queue
- `hook_group_presave()` — non-admin groups default to unpublished (status=0)
- Created `pending_groups` view at `/admin/groups/pending` — lists unpublished groups with operations column
- `hook_group_insert()` + `hook_mail()` — sends email notification to sitemanager role on new pending group

### 2.6 Group Submission Guidelines
- `hook_form_alter()` injects guidelines markup at top of group creation form with `.group-submission-guidelines` CSS class

## Verification
- **Playwright Test Suite**: `tests/e2e/phase2-groups.spec.ts`

## Status: COMPLETE

---

# Phase 3: Taxonomy, Discovery & Feeds - Build Log

## Overview
Implement sitewide tags aggregation, events calendar with iCal feeds, hot content page, front page promotion via Flag module, and per-group RSS feeds.

## Pre-requisite
- Database backed up to `backups/phase3-pre.sql.gz`

## Changes

### 3.1 Sitewide Tags
- Enabled `social_tagging` module — adds tagging to Topics via `social_tagging` vocabulary
- Created `tags_aggregation` View at `/tags` — lists Topics filtered by tag

### 3.2 Events Calendar & iCal Feeds
- Created custom module `pl_discovery` (`web/modules/custom/pl_discovery/`):
  - `IcalController` with 3 endpoints:
    - `/upcoming-events/ical` — site-wide events iCal feed
    - `/group/{group}/events/ical` — group-scoped events iCal
    - `/user/{user}/events/ical` — user-enrolled events iCal
  - `hook_cron()` — recomputes hot content scores (comments×3 + views×0.5)
  - `hook_views_data()` — exposes `pl_discovery_hot_score` table to Views
  - `hook_node_insert()` — seeds hot score entry for new published nodes
- Enabled `statistics` module for node view count tracking

### 3.3 Hot Content
- Created `hot_content` View at `/hot` — lists content with exposed Content Type filter

### 3.4 Front-Page Promotion
- Enabled `flag_count` module
- Created `promote_homepage` Flag (global, for Topic/Event/Page content types)
  - Flagging permissions granted to `contentmanager` and `sitemanager` roles
- Created `promoted_content` View at `/admin/content/promoted` — lists flagged content

### 3.5 Per-Group RSS Feeds
- Created `group_rss_feed` View at `/group/{id}/stream/feed` — RSS feed of group content

## Verification
- **Playwright Test Suite**: `tests/e2e/phase3-discovery.spec.ts` — 14/14 tests pass (1.5 min)
  - Tags: View exists and loads, social_tagging vocabulary active
  - Events: filter by type, calendar display, site + group iCal feeds, user iCal endpoint
  - Hot Content: page loads, content type filter works, no errors
  - Promoted Content: flag/unflag flow, admin page loads
  - RSS: group feed endpoint works

## Status: COMPLETE
