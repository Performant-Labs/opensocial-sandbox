# Phase 5 Plan — Notifications & Subscriptions

**Objective**: Match g.d.o's notification model — email subscriptions by group/thread/author/content type, per-post opt-out, configurable frequency.

**Build log numbering**: Steps 800–899

---

## Existing Infrastructure (already built into Open Social)

Open Social ships with a **complete notification pipeline** that covers ~80% of this phase's requirements. Deep research reveals:

### Email Frequency System (already implemented)

The `activity_send_email` module has a **plugin-based frequency system** with four built-in modes:

| Plugin | Interval | Behaviour |
|---|---|---|
| `Immediately` | 0s | Sends email on activity creation |
| `Daily` | 86400s | Queues to `user_activity_digest` table, sent by `ActivityDigestWorker` on cron |
| `Weekly` | 604800s | Same digest mechanism, weekly interval |
| `None` | — | Suppresses all email |

Frequency preferences are stored **per message template, per user** — e.g. "Topics in my group → Daily" but "Replies to my comments → Immediately".

### Queue Architecture

The `social_advanced_queue` module (already enabled) overrides Drupal's core queue with the Advanced Queue contrib module:
- `EmailActivitySendAdvancedQueue` creates `Job` objects and enqueues them via `Queue::load('default')`
- Benefits: persistent job tracking, retry logic, priority, admin UI
- Digest worker (`ActivityDigestWorker`) runs via cron, batches queued activities per user, renders using `digestmail.html.twig`

### Activity Pipeline (3 layers)

1. **Activity Creation** (`activity_creator`) — Creates Activity entities linked to Message entities (38+ templates already defined for group, content, comment, mention, event activities)
2. **Activity Dispatch** (`activity_send`) — Routes to destinations (email, push, on-site notification)
3. **Email Delivery** (`activity_send_email`) — Handles frequency, queuing, digest rendering

### Existing Subscription Mechanisms

| Feature | Module | Status | Maps to g.d.o |
|---|---|---|---|
| Thread follow | `social_follow_content` (`follow_content` flag) | **Enabled** | Thread subscription |
| Group mute | `mute_group_notifications` flag | **Enabled** | Group notification toggle |
| Tag follow | `social_follow_tag` | **Disabled** | Content type/tag subscription |
| Author follow | `social_follow_user` | **Disabled** | Author subscription |
| Message notify | `message_notify` | **Disabled** | Email delivery of messages |

### Existing Routes

- `/notifications/{uid}` — On-site notification stream
- `/edit-email-settings` — Redirects to user edit form (has per-template frequency settings)

---

## What's Missing (custom work required)

Only **3 features** need custom implementation:

### 1. Per-post opt-out checkbox
"Do not send notifications for this update" on Topic, Event, and Page create/edit forms. Needs:
- `hook_form_node_form_alter()` to add checkbox
- `hook_activity_send_email_alter()` or equivalent to suppress dispatch

### 2. Subscription management page
A unified view at `/user/{uid}/notification-settings` showing:
- Active subscription count (followed content + tags + users)
- Table of all subscriptions with remove action
- "Temporarily disable all" toggle
- "Cancel all" action

### 3. Enable and configure disabled modules
`social_follow_tag`, `social_follow_user`, `message_notify` — just need enabling and permission grants.

---

## Proposed Steps

### 5.1 Enable sub-modules

**Step 800** — Enable notification sub-modules:
```bash
ddev drush en social_follow_tag social_follow_user message_notify -y
ddev drush cr
```

**Step 805** — Grant permissions:
```bash
ddev drush role:perm:add authenticated "flag follow_content"
ddev drush role:perm:add authenticated "unflag follow_content"
```

---

### 5.2 Custom module: `pl_notifications`

**Step 810** — Create `web/modules/custom/pl_notifications/` with:
- `pl_notifications.info.yml`
- `pl_notifications.module` — Per-post opt-out checkbox via `hook_form_node_form_alter()`; suppression via `hook_activity_send_email_alter()`
- `pl_notifications.routing.yml` — Route for `/user/{user}/notification-settings`
- `src/Controller/NotificationSettingsController.php` — Management page
- `src/Form/NotificationSettingsForm.php` — Add subscription form, disable/cancel actions

**Step 820** — Enable: `ddev drush en pl_notifications -y && ddev restart`

---

### 5.3 User account tab

**Step 830** — Add "Notifications" tab to user profile via `pl_notifications.links.task.yml`

---

### 5.4 Configuration export

**Step 840** — Export config, clear caches:
```bash
ddev drush cex -y
ddev drush cr
```

---

## Verification Plan

### Automated Tests — `tests/e2e/phase5-notifications.spec.ts`

| Test ID | Description |
|---|---|
| `notify.group.subscribe` | User subscribes to a group; new Topic triggers email (check Mailpit API) |
| `notify.thread.subscribe` | User follows a thread; reply triggers email |
| `notify.optout.post` | Topic with "Do not send notifications" checked sends no email |
| `notify.frequency.digest` | User with "Daily digest" does not receive immediate email |
| `notify.manage.page` | Management page shows active subscriptions and allows removal |
| `notify.disable.all` | "Temporarily disable all" stops notification emails |
| `notify.cancel.all` | "Cancel all" removes all subscriptions |
| `notify.rss.group` | `/group/{id}/stream/feed` returns content (already tested in Phase 4) |

**Email assertions**: Mailpit API at `https://pl-opensocial-rework.ddev.site:8302/api/v1/messages`

**Run**: `./node_modules/.bin/playwright test e2e/phase5-notifications.spec.ts --reporter=list`

---

## Complexity Assessment

**Medium**. Down from the original estimate because ~80% is already built:
- ✅ Email frequency (Immediately/Daily/Weekly/None) — built in
- ✅ Digest processing — built in (`ActivityDigestWorker` + `social_advanced_queue`)
- ✅ Thread subscription — built in (`social_follow_content`)
- ✅ On-site notifications — built in
- 🔧 Enable: tag/author follow, `message_notify`
- 🛠️ Build: per-post opt-out, subscription management page
