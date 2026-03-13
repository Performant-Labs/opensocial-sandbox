import { test, expect, Page } from '@playwright/test';

/**
 * Phase 7: User Profiles & History Stats
 *
 * Tests cover: profile page rendering, profile field display, profile editing,
 * contribution stats block, profile completeness block, and image upload.
 */

const timestamp = () => Date.now();

/** Helper: login as a specific user. */
async function loginAs(page: Page, username: string, password: string) {
    await page.goto('/user/login');
    await page.fill('#edit-name-or-mail', username);
    await page.fill('#edit-pass', password);
    await page.click('#edit-submit');
    await expect(page.locator('#search-input, .profile-name, .avatar').first()).toBeVisible();
}

test.describe('Phase 7: User Profiles & History Stats', () => {

    test.beforeEach(async ({ page }) => {
        await loginAs(page, 'admin', 'admin');
    });

    // ---------------------------------------------------------------
    // Test 1: Profile page loads
    // ---------------------------------------------------------------
    test('profile.page.loads: Admin profile page renders without errors', async ({ page }) => {
        await page.goto('/user/1');
        await page.waitForTimeout(2000);

        const content = await page.content();
        expect(content).not.toContain('Access denied');
        expect(content).not.toContain('Page not found');
        // Should contain the user's name somewhere.
        expect(content.toLowerCase()).toContain('admin');
    });

    // ---------------------------------------------------------------
    // Test 2: Profile fields display
    // ---------------------------------------------------------------
    test('profile.fields.display: First name, organization display on profile', async ({ page }) => {
        await page.goto('/user/1');
        await page.waitForTimeout(2000);

        const content = await page.content();
        // Admin profile was populated with "Site Administrator" and "Open Social Foundation".
        expect(content).toContain('Site');
        expect(content).toContain('Administrator');
    });

    // ---------------------------------------------------------------
    // Test 3: Profile edit works
    // ---------------------------------------------------------------
    test('profile.edit.works: Admin can edit their own profile', async ({ page }) => {
        // Navigate to the user edit page.
        await page.goto('/user/1/edit');
        await page.waitForTimeout(2000);

        // The edit page should load without access denied.
        const content = await page.content();
        expect(content).not.toContain('Access denied');

        // Should show edit form fields (mail, name, or profile fields).
        const hasEditForm = content.includes('edit-mail') ||
            content.includes('edit-name') ||
            content.includes('edit-field-profile') ||
            content.includes('edit-pass');
        expect(hasEditForm).toBeTruthy();
    });

    // ---------------------------------------------------------------
    // Test 4: Contribution stats block renders
    // ---------------------------------------------------------------
    test('profile.stats.block: Contribution stats block shows on profile', async ({ page }) => {
        await page.goto('/user/1');
        await page.waitForTimeout(2000);

        // Look for the stats block.
        const statsBlock = page.locator('#pl-contribution-stats, .pl-contribution-stats');
        await expect(statsBlock.first()).toBeVisible({ timeout: 10000 });

        // Check that stat counts are rendered.
        const statsContent = await statsBlock.first().innerHTML();
        expect(statsContent).toContain('Topics');
        expect(statsContent).toContain('Comments');
        expect(statsContent).toContain('Groups');
        expect(statsContent).toContain('Days Active');
    });

    // ---------------------------------------------------------------
    // Test 5: Profile completeness block renders
    // ---------------------------------------------------------------
    test('profile.completeness.block: Profile completeness block shows percentage', async ({ page }) => {
        await page.goto('/user/1');
        await page.waitForTimeout(2000);

        // Look for the completeness block.
        const compBlock = page.locator('#pl-profile-completeness, .pl-profile-completeness');
        await expect(compBlock.first()).toBeVisible({ timeout: 10000 });

        // Should show a percentage.
        const compContent = await compBlock.first().innerHTML();
        expect(compContent).toContain('%');
        expect(compContent).toContain('complete');
    });

    // ---------------------------------------------------------------
    // Test 6: Stats counts are numeric
    // ---------------------------------------------------------------
    test('profile.stats.numeric: Stats display numeric values', async ({ page }) => {
        await page.goto('/user/1');
        await page.waitForTimeout(2000);

        // Check individual stat items have numeric values.
        const statCounts = page.locator('.stat-count');
        const count = await statCounts.count();
        expect(count).toBeGreaterThanOrEqual(4);

        // Each stat should be a number.
        for (let i = 0; i < count; i++) {
            const text = await statCounts.nth(i).textContent();
            expect(text?.trim()).toMatch(/^\d+$/);
        }
    });

    // ---------------------------------------------------------------
    // Test 7: Completeness shows missing fields
    // ---------------------------------------------------------------
    test('profile.completeness.missing: Completeness block lists missing fields', async ({ page }) => {
        await page.goto('/user/1');
        await page.waitForTimeout(2000);

        const compBlock = page.locator('.pl-profile-completeness');
        await expect(compBlock.first()).toBeVisible({ timeout: 10000 });

        // Since we only populated 4-5 of 9 fields, there should be missing fields.
        const content = await compBlock.first().innerHTML();
        expect(content).toContain('Missing fields');
    });

    // ---------------------------------------------------------------
    // Test 8: Second user's profile renders
    // ---------------------------------------------------------------
    test('profile.other.user: Another user profile page renders', async ({ page }) => {
        const ts = timestamp();
        const username = `profiletest_${ts}`;

        // Create a test user.
        await page.goto('/admin/people/create');
        await page.fill('#edit-name', username);
        await page.fill('#edit-mail', `${username}@example.com`);
        await page.fill('#edit-pass-pass1', 'Test1234!');
        await page.fill('#edit-pass-pass2', 'Test1234!');
        const notify = page.locator('#edit-notify');
        if (await notify.isVisible().catch(() => false) && await notify.isChecked()) {
            await notify.click();
        }
        await page.click('#edit-submit');
        await page.waitForTimeout(3000);

        // After creation, Drupal may redirect to admin/people or user page.
        // Check the current URL and page content for the uid.
        let uid = page.url().match(/\/user\/(\d+)/)?.[1];

        if (!uid) {
            // Search the people admin page for the new user.
            await page.goto(`/admin/people?search_api_fulltext=${username}`);
            await page.waitForTimeout(2000);
            const userLink = page.locator(`a[href*="/user/"]:has-text("${username}")`).first();
            if (await userLink.isVisible({ timeout: 5000 }).catch(() => false)) {
                const href = await userLink.getAttribute('href') || '';
                uid = href.match(/\/user\/(\d+)/)?.[1];
            }
        }

        // We may not always get the uid; just test that a non-admin profile works.
        // Fall back to uid 2 if we created any users before.
        if (!uid) {
            uid = '2';
        }

        // Visit that user's profile.
        await page.goto(`/user/${uid}`);
        await page.waitForTimeout(2000);

        const content = await page.content();
        expect(content).not.toContain('Access denied');
        expect(content).not.toContain('Page not found');

        // The stats block should be visible.
        const statsBlock = page.locator('#pl-contribution-stats, .pl-contribution-stats');
        await expect(statsBlock.first()).toBeVisible({ timeout: 10000 });
    });

});
