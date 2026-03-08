import { test as base } from '@playwright/test';

type GroupFixtures = {
    createGroup: (options: { name: string; type: string }) => Promise<void>;
};

export const test = base.extend<GroupFixtures>({
    createGroup: async ({ page }, use) => {
        const create = async ({ name, type }: { name: string; type: string }) => {
            await page.goto('/group/add');
            // Implementation depends on group types created in Phase 2
        };
        await use(create);
    },
});
