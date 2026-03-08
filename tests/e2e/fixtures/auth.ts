import { test as base } from '@playwright/test';

// Roles defined in the implementation plan
export type UserRole = 'admin' | 'editor' | 'member' | 'anonymous';

type AuthFixtures = {
    loginAs: (role: UserRole) => Promise<void>;
};

export const test = base.extend<AuthFixtures>({
    loginAs: async ({ page }, use) => {
        const login = async (role: UserRole) => {
            if (role === 'anonymous') {
                await page.context().clearCookies();
                return;
            }

            await page.goto('/user/login');
            if (role === 'admin') {
                await page.fill('#edit-name', 'admin');
                await page.fill('#edit-pass', 'admin');
                await page.click('#edit-submit');
            }
        };
        await use(login);
    },
});
