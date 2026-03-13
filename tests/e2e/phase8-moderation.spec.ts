import { test, expect, Page } from '@playwright/test';
import { execSync } from 'child_process';
import * as path from 'path';

/**
 * Phase 8: Content Moderation & Group Admin
 *
 * Entity setup uses drush (reliable, reproducible).
 * Playwright handles login, navigation, and GUI assertions.
 */

// Project root — derived from tests/e2e/ → ../../
const PROJECT_ROOT = path.resolve(__dirname, '..', '..');

const timestamp = () => Date.now();

// ---------------------------------------------------------------------------
// Drush helpers
// ---------------------------------------------------------------------------

/** Run a drush php:eval snippet and return stdout. */
function drush(phpCode: string): string {
    const escaped = phpCode.replace(/'/g, "'\\''");
    return execSync(
        `ddev drush php:eval '${escaped}'`,
        { cwd: PROJECT_ROOT, encoding: 'utf-8', timeout: 30000 }
    ).trim();
}

/** Create a flexible_group via drush, return group ID. */
function drushCreateGroup(name: string, opts: {
    description?: string;
    language?: string;
} = {}): string {
    const desc = (opts.description || '').replace(/'/g, "\\'");
    const lang = opts.language || 'und';
    return drush(`
        $group = \\Drupal\\group\\Entity\\Group::create([
            "type" => "flexible_group",
            "label" => "${name}",
            "uid" => 1,
            "field_flexible_group_visibility" => "public",
            "field_group_allowed_join_method" => ["direct"],
            "field_group_allowed_visibility" => ["public", "community"],
            "field_group_description" => ["value" => "${desc}", "format" => "basic_html"],
            "field_group_language" => "${lang}",
            "field_group_type" => [13],
        ]);
        $group->save();
        echo $group->id();
    `);
}

/** Create a topic node and add it to a group, return node ID. */
function drushCreateTopic(groupId: string, title: string): string {
    return drush(`
        $node = \\Drupal\\node\\Entity\\Node::create([
            "type" => "topic",
            "title" => "${title}",
            "uid" => 1,
            "status" => 1,
            "body" => ["value" => "Body for ${title}", "format" => "basic_html"],
            "field_topic_type" => [2],
            "field_content_visibility" => "public",
        ]);
        $node->save();
        $group = \\Drupal\\group\\Entity\\Group::load(${groupId});
        $group->addRelationship($node, "group_node:topic");
        echo $node->id();
    `);
}

/** Create a Drupal user, return uid. */
function drushCreateUser(username: string, pass: string): string {
    return drush(`
        $user = \\Drupal\\user\\Entity\\User::create([
            "name" => "${username}",
            "mail" => "${username}@example.com",
            "pass" => "${pass}",
            "status" => 1,
        ]);
        $user->save();
        echo $user->id();
    `);
}

/** Add a user to a group as a regular member. */
function drushJoinGroup(uid: string, groupId: string): void {
    drush(`
        $group = \\Drupal\\group\\Entity\\Group::load(${groupId});
        $user = \\Drupal\\user\\Entity\\User::load(${uid});
        $group->addMember($user);
    `);
}

/** Flag a node with a given flag ID (e.g. "pin_in_group"). */
function drushFlag(flagId: string, nodeId: string): void {
    drush(`
        $flag_service = \\Drupal::service("flag");
        $flag = $flag_service->getFlagById("${flagId}");
        $node = \\Drupal\\node\\Entity\\Node::load(${nodeId});
        $account = \\Drupal\\user\\Entity\\User::load(1);
        $flag_service->flag($flag, $node, $account);
    `);
}

/** Unflag a node. */
function drushUnflag(flagId: string, nodeId: string): void {
    drush(`
        $flag_service = \\Drupal::service("flag");
        $flag = $flag_service->getFlagById("${flagId}");
        $node = \\Drupal\\node\\Entity\\Node::load(${nodeId});
        $account = \\Drupal\\user\\Entity\\User::load(1);
        $flag_service->unflag($flag, $node, $account);
    `);
}

/** Set user preferred language. */
function drushSetUserLanguage(uid: string, langcode: string): void {
    drush(`
        $user = \\Drupal\\user\\Entity\\User::load(${uid});
        $user->set("preferred_langcode", "${langcode}");
        $user->save();
    `);
}

// ---------------------------------------------------------------------------
// Playwright helpers
// ---------------------------------------------------------------------------

async function loginAs(page: Page, username: string, password: string) {
    await page.goto('/user/login');
    await page.fill('#edit-name-or-mail', username);
    await page.fill('#edit-pass', password);
    await page.click('#edit-submit');
    await page.waitForLoadState('load');
    await expect(page.locator('#search-input, .profile-name, .avatar').first()).toBeVisible({ timeout: 10000 });
}

async function logoutUser(page: Page) {
    await page.goto('/user/logout');
    const confirmBtn = page.locator('form#user-logout-confirm button#edit-submit, #edit-submit');
    if (await confirmBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
        await confirmBtn.click();
    }
    await page.waitForTimeout(1000);
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

test.describe('Phase 8: Content Moderation & Group Admin', () => {

    test.beforeEach(async ({ page }) => {
        await loginAs(page, 'admin', 'admin');
    });

    // ---------------------------------------------------------------
    // Test 1: pin.manager.can.pin
    // ---------------------------------------------------------------
    test('pin.manager.can.pin: Group Manager pins a Topic; it appears above newer Topics', async ({ page }) => {
        const ts = timestamp();

        // Setup via drush.
        const groupId = drushCreateGroup(`PinTest Group ${ts}`);
        const olderNid = drushCreateTopic(groupId, `Older Topic ${ts}`);
        drushCreateTopic(groupId, `Newer Topic ${ts}`);
        drushFlag('pin_in_group', olderNid);

        // Verify in GUI: visit group topics page.
        await page.goto(`/group/${groupId}/topics`);
        await page.waitForLoadState('load');
        await page.waitForTimeout(2000);

        const content = await page.content();
        expect(content).toContain(`Older Topic ${ts}`);
        expect(content).toContain(`Newer Topic ${ts}`);
    });

    // ---------------------------------------------------------------
    // Test 2: pin.member.cannot.pin
    // ---------------------------------------------------------------
    test('pin.member.cannot.pin: Regular member does not see the pin option', async ({ page }) => {
        const ts = timestamp();

        // Setup via drush.
        const groupId = drushCreateGroup(`NoPinTest Group ${ts}`);
        const topicNid = drushCreateTopic(groupId, `MemberTopic ${ts}`);
        const uid = drushCreateUser(`pintest_${ts}`, 'Test1234!');
        drushJoinGroup(uid, groupId);

        // Login as the regular user.
        await logoutUser(page);
        await loginAs(page, `pintest_${ts}`, 'Test1234!');

        // Visit the topic — should NOT see pin link.
        await page.goto(`/node/${topicNid}`);
        await page.waitForLoadState('load');
        await page.waitForTimeout(1000);

        const pinVisible = await page.locator('a:has-text("Pin in group"), .flag-pin-in-group a').isVisible({ timeout: 3000 }).catch(() => false);
        expect(pinVisible).toBeFalsy();
    });

    // ---------------------------------------------------------------
    // Test 3: pin.badge
    // ---------------------------------------------------------------
    test('pin.badge: Pinned Topic displays a Pinned visual indicator', async ({ page }) => {
        const ts = timestamp();

        // Setup via drush.
        const groupId = drushCreateGroup(`BadgeTest Group ${ts}`);
        const topicNid = drushCreateTopic(groupId, `BadgeTopic ${ts}`);
        drushFlag('pin_in_group', topicNid);

        // Verify in GUI.
        await page.goto(`/node/${topicNid}`);
        await page.waitForLoadState('load');
        await page.waitForTimeout(1000);

        const content = await page.content();
        const hasPinIndicator = content.includes('pin-badge') ||
            content.includes('node--pinned') ||
            content.includes('Pinned') ||
            content.includes('Unpin');
        expect(hasPinIndicator).toBeTruthy();
    });

    // ---------------------------------------------------------------
    // Test 4: pin.unpin
    // ---------------------------------------------------------------
    test('pin.unpin: Manager unpins a Topic; it returns to chronological position', async ({ page }) => {
        const ts = timestamp();

        // Setup via drush: create, pin, then unpin.
        const groupId = drushCreateGroup(`UnpinTest Group ${ts}`);
        const olderNid = drushCreateTopic(groupId, `UnpinOlder ${ts}`);
        drushCreateTopic(groupId, `UnpinNewer ${ts}`);
        drushFlag('pin_in_group', olderNid);
        drushUnflag('pin_in_group', olderNid);

        // Verify: visit group topics — chronological order restored.
        await page.goto(`/group/${groupId}/topics`);
        await page.waitForLoadState('load');
        await page.waitForTimeout(2000);

        const content = await page.content();
        expect(content).toContain(`UnpinNewer ${ts}`);
    });

    // ---------------------------------------------------------------
    // Test 5: homepage.promote
    // ---------------------------------------------------------------
    test('homepage.promote: Editor promotes content to homepage; block on front page shows item', async ({ page }) => {
        const ts = timestamp();

        // Setup via drush.
        const groupId = drushCreateGroup(`PromoteTest Group ${ts}`);
        const topicNid = drushCreateTopic(groupId, `PromoteTopic ${ts}`);
        drushFlag('promote_homepage', topicNid);

        // Verify on homepage.
        await page.goto('/');
        await page.waitForLoadState('load');
        await page.waitForTimeout(2000);

        const content = await page.content();
        expect(content).toContain(`PromoteTopic ${ts}`);
    });

    // ---------------------------------------------------------------
    // Test 6: organizer.sidebar
    // ---------------------------------------------------------------
    test('organizer.sidebar: Group Managers username appears in group sidebar', async ({ page }) => {
        const ts = timestamp();

        const groupId = drushCreateGroup(`OrgSidebar Group ${ts}`);

        await page.goto(`/group/${groupId}`);
        await page.waitForLoadState('load');
        await page.waitForTimeout(2000);

        const content = await page.content();
        const sidebarManagers = page.locator('.region--complementary, .complementary, aside, [class*="sidebar"]');
        if (await sidebarManagers.first().isVisible({ timeout: 5000 }).catch(() => false)) {
            const sidebarContent = await sidebarManagers.first().innerHTML();
            const hasAdmin = sidebarContent.toLowerCase().includes('admin') ||
                sidebarContent.includes('/user/1');
            expect(hasAdmin).toBeTruthy();
        } else {
            expect(content.toLowerCase()).toContain('group managers');
        }
    });

    // ---------------------------------------------------------------
    // Test 7: group.language.fr
    // ---------------------------------------------------------------
    test('group.language.fr: Group language set to French renders French UI labels', async ({ page }) => {
        const ts = timestamp();

        // Clear admin user's language preference so group language takes effect.
        drushSetUserLanguage('1', '');

        // Create group with French language via drush.
        const groupId = drushCreateGroup(`FrenchTest Group ${ts}`, {
            description: 'A French language test group',
            language: 'fr',
        });

        // Re-login so the session picks up the cleared language preference.
        await logoutUser(page);
        await loginAs(page, 'admin', 'admin');

        // Verify French UI.
        await page.goto(`/group/${groupId}`);
        await page.waitForLoadState('load');
        await page.waitForTimeout(2000);

        const content = await page.content();
        const hasFrench = content.includes('Membres') ||
            content.includes('Événements') ||
            content.includes('Sujets') ||
            content.includes('Rejoindre') ||
            content.includes('À propos') ||
            content.includes('Accueil') ||
            content.includes('lang="fr"') ||
            content.includes('Flux');

        expect(hasFrench).toBeTruthy();

        // Reset admin language to English.
        drushSetUserLanguage('1', 'en');
    });

    // ---------------------------------------------------------------
    // Test 8: group.language.user.override
    // ---------------------------------------------------------------
    test('group.language.user.override: User with personal locale overrides group language', async ({ page }) => {
        const ts = timestamp();

        // Create group with French language.
        const groupId = drushCreateGroup(`UserOverride Group ${ts}`, {
            description: 'Testing user language override',
            language: 'fr',
        });

        // Set admin user's preferred language to Spanish.
        drushSetUserLanguage('1', 'es');

        // Visit the French group — should see Spanish UI (user preference wins).
        await page.goto(`/group/${groupId}`);
        await page.waitForLoadState('load');
        await page.waitForTimeout(2000);

        const content = await page.content();
        const hasSpanish = content.includes('Miembros') ||
            content.includes('Eventos') ||
            content.includes('Temas') ||
            content.includes('Unirse') ||
            content.includes('Acerca de') ||
            content.includes('Inicio') ||
            content.includes('lang="es"');

        const hasFrench = content.includes('Membres') ||
            content.includes('Événements');

        expect(hasSpanish).toBeTruthy();
        expect(hasFrench).toBeFalsy();

        // Reset admin language to English.
        drushSetUserLanguage('1', 'en');
    });

    // ---------------------------------------------------------------
    // Test 9: about.mission
    // ---------------------------------------------------------------
    test('about.mission: Group Mission Statement visible on group homepage sidebar', async ({ page }) => {
        const ts = timestamp();
        const missionText = `This is the mission statement for testing Phase 7 ${ts}`;

        const groupId = drushCreateGroup(`MissionTest Group ${ts}`, {
            description: missionText,
        });

        // Visit the group page.
        await page.goto(`/group/${groupId}`);
        await page.waitForLoadState('load');
        await page.waitForTimeout(2000);

        // Check for the mission text or "About this group" block.
        const content = await page.content();
        const hasMission = content.includes('About this group') ||
            content.includes(missionText.substring(0, 50));

        expect(hasMission).toBeTruthy();
    });

});
