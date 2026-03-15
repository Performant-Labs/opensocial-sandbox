# Feature Tour

A guided walkthrough of the platform's most important capabilities — both the Open Social foundation and custom Performant Labs extensions.

---

## Content Types

### Topics

The primary discussion format. Users write posts with rich text (CKEditor 5), attach files, and tag content with taxonomy terms from the `social_tagging` vocabulary. Topics appear in group streams, user profiles, and the community-wide activity feed.

**Wiki links:** The `pl_opensocial_wiki` module enables `[[Title]]` syntax inside topic bodies. When rendered, these become clickable links to the matching topic — connecting knowledge across the platform without requiring manual URL management.

### Events

Calendar-based content with start/end dates, enrollment management, and optional capacity limits (`social_event_max_enroll`). Events support multiple event types (DrupalCon, Sprint, User group meeting, DrupalCamp, etc.) from the `event_types` taxonomy.

Anonymous enrollment is available via `social_event_an_enroll` when configured.

### Pages

Static content for landing pages, about sections, and editorial content.

---

## Groups

All groups use the **Flexible Group** bundle, which provides configurable visibility and join methods.

### Visibility levels

| Level | Behavior |
|---|---|
| **Public** | Anyone (including anonymous) can view group content |
| **Community** | Only logged-in users can view |
| **Members** | Only group members can view (used for confidential groups like Leadership Council) |

### Join methods

| Method | Behavior |
|---|---|
| **Direct** | Users join instantly by clicking "Join" |
| **Added** | Invitation-only — managers must add members (used for Core Committers, Leadership Council) |
| **Request** | Users submit a membership request; managers approve or reject (demonstrated by elena_garcia's pending request to Core Committers) |

### Group types

Groups are categorized by a `group_type` taxonomy: Geographical, Working Group, Distribution, Event Planning, and Archive. This classification appears on group cards and enables filtering.

### Group language

The `pl_group_language` module adds a language field (`field_group_language`) to groups. When a user visits a group with a language set (e.g., Drupal France → `fr`), the interface language switches to match. This is group-level language negotiation on top of Open Social's per-user language preferences.

### Group mission block

The `pl_group_mission` module displays a summary of the group's description as a sidebar block on all group pages — giving visitors immediate context about the group's purpose.

### Content pinning

The `pl_group_pin` module lets group managers pin important topics to the top of the group's content stream, above the chronological feed. Uses the Flag module's `pin_in_group` flag.

### Archive enforcement

The `pl_group_extras` module handles archived groups: submission guidelines displayed to members, moderation defaults, and restrictions on posting to archived groups (e.g., Legacy Infrastructure).

---

## Multi-Group Posting

The `pl_multigroup` module allows a single Topic or Event to belong to multiple groups simultaneously. When creating content, users select one or more groups. The content appears in each group's stream without duplication.

Cross-posted content shows badges indicating all groups it belongs to.

---

## User Profiles

### Profile fields

Each user has a profile with:
- First/last name, organization, function/role
- Self-introduction (rich text)
- Summary (short bio)
- Profile image

### Contribution statistics

The `pl_profile_stats` module adds a statistics block to user profiles showing contribution counts: topics authored, comments made, events created, and group memberships. This gives visitors a quick sense of each user's engagement level.

### Profile completeness

Users without a profile image or self-introduction (e.g., alex_novak) are prompted to complete their profile. This is surfaced through the profile stats indicator.

---

## Discovery & Content Surfacing

The `pl_discovery` module provides several content discovery features:

- **Hot content scoring** — Algorithmic ranking based on recency, comments, and likes
- **Promoted content** — Uses the `promote_homepage` flag to feature content on the homepage
- **Tags aggregation view** — Browse content by taxonomy tag
- **Per-group RSS feeds** — Each group exposes an RSS feed for external consumption
- **iCal feeds** — Calendar events exportable as iCal for subscribers

---

## Multilingual Support

### 15 languages

The platform ships with 15 configured languages: English, German, Spanish, French, Italian, Japanese, Korean, Dutch, Polish, Portuguese, Russian, Turkish, Ukrainian, Chinese (Simplified), and Arabic.

### User language preferences

Via `social_language`, each user sets a preferred language in their account settings. Activity streams, notifications, and email digests render in the user's chosen language.

### Content translation

Topics and Events can be translated into any enabled language. The `content_translation` module provides the translation workflow.

### Group-level switching

As described above, `pl_group_language` overrides the interface language based on the group context — so visiting Drupal France switches the UI to French regardless of the user's personal preference.

---

## Notifications

### Core notifications

Open Social provides activity-stream notifications for:
- New content in groups you belong to
- Comments on content you authored or follow
- Mentions (`@username`)
- Event enrollment updates
- Membership changes

### Custom notification controls

The `pl_notifications` module extends this with:
- **Per-post opt-out** — Unsubscribe from notifications on a specific topic without leaving the group
- **Subscription management page** — A centralized view of all content you're subscribed to, with bulk unsubscribe

---

## Roles & Permissions

| Role | Capabilities |
|---|---|
| **Authenticated** | Create topics, events; join groups; comment; manage own profile |
| **Verified** | Same as Authenticated, plus follow content via flags |
| **Content Manager** | Edit/delete any content; manage taxonomy terms; moderate comments |
| **Site Manager** | All Content Manager permissions plus user management, config translations, group type management |
| **Administrator** | Full access |

Group-level roles (Group Manager, Group Member) add per-group moderation capabilities.

---

## Search & Facets

Search is powered by **Search API** with a database backend. Faceted search lets users filter results by content type, group, tag, date, and author. The `facets` and `better_exposed_filters` modules provide the filtering UI.

> **Note:** Solr is not configured. If needed for performance at scale, a Solr container can be added and the Search API backend switched.

---

## Technical Details

| Component | Version |
|---|---|
| Drupal core | 10.6.x |
| Open Social | 13.0.0 |
| PHP | 8.3 |
| Database | MariaDB 11.8 |
| Theme | SocialBlue (based on SocialBase → Bootstrap) |

### Deployment

Production runs in Docker (PHP-FPM container + MariaDB container) behind the host's nginx with SSL. See `INSTALL_ON_PROD.md` for setup and `DOCKER_DEPLOY.md` for technical reference.

### Custom modules

| Module | Purpose |
|---|---|
| `pl_discovery` | Hot scoring, promoted content, iCal/RSS feeds, tag views |
| `pl_group_extras` | Archive enforcement, submission guidelines, moderation defaults |
| `pl_group_language` | Group-level interface language switching |
| `pl_group_mission` | Group mission statement sidebar block |
| `pl_group_pin` | Pin topics to top of group stream |
| `pl_multigroup` | Post content to multiple groups simultaneously |
| `pl_notifications` | Per-post opt-out, subscription management |
| `pl_opensocial_wiki` | `[[Title]]` wiki-style linking between topics |
| `pl_profile_stats` | Contribution stats and profile completeness indicator |
