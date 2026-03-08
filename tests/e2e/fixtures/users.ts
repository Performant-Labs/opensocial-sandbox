import { test as base } from '@playwright/test';

type UserFixtures = {
    createUser: (options: { username: string; role: string }) => Promise<void>;
};

export const test = base.extend<UserFixtures>({
    createUser: async ({ page }, use) => {
        const create = async ({ username, role }: { username: string; role: string }) => {
            // Implementation for dynamic user creation
        };
        await use(create);
    },
});
