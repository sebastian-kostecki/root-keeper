import { expect, test as setup } from '@playwright/test';

const authFile = 'playwright/.auth/user.json';

setup('sign in once and save the session', async ({ page }) => {
    const username = process.env.E2E_USERNAME;
    const password = process.env.E2E_PASSWORD;
    if (!username || !password) {
        throw new Error(
            'Set E2E_USERNAME and E2E_PASSWORD (see context/foundation/test-stack.md, ## E2E)',
        );
    }

    await page.goto('/login');
    // The Inertia form drops input typed before it hydrates. Retry fill + submit
    // until the submit leaves /login; clear first so a same-value fill still fires
    // a change event. Signing in twice changes nothing, so the retry is safe.
    await expect(async () => {
        await page.getByLabel('Adres e-mail').fill('');
        await page.getByLabel('Adres e-mail').fill(username);
        await page.getByLabel('Hasło', { exact: true }).fill('');
        await page.getByLabel('Hasło', { exact: true }).fill(password);
        await page
            .getByRole('button', { name: 'Zaloguj się', exact: true })
            .click();
        await page.waitForURL((url) => !url.pathname.startsWith('/login'), {
            timeout: 5_000,
        });
    }).toPass();
    await expect(page).toHaveURL(/\/persons/);
    await expect(
        page.getByRole('heading', { name: 'Osoby', level: 2, exact: true }),
    ).toBeVisible();

    await page.context().storageState({ path: authFile });
});
