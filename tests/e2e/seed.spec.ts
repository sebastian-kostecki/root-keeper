// Seed test — the pattern every generated E2E test copies.
// Risk: auth-gate-roundtrip — a signed-out visitor must not reach tree data
// (persons, sources, tree); after signing in they land on the page they asked for.
// Not in context/foundation/test-plan.md (its risks are server-side); chosen as the
// fastest browser-level flow that is green without mocks.
import { expect, test } from '@playwright/test';

// This test's risk IS the signed-out path, so it opts out of the saved session.
test.use({ storageState: { cookies: [], origins: [] } });

test('auth-gate-roundtrip: signed-out visit to /persons redirects to /login, sign-in lands on /persons', async ({
    page,
}) => {
    const username = process.env.E2E_USERNAME;
    const password = process.env.E2E_PASSWORD;
    if (!username || !password) {
        throw new Error(
            'Set E2E_USERNAME and E2E_PASSWORD (see ## E2E in test-stack.md)',
        );
    }

    await page.goto('/persons');
    await expect(page).toHaveURL(/\/login$/);
    await expect(
        page.getByRole('heading', { name: 'Zaloguj się', level: 1 }),
    ).toBeVisible();
    // Nothing from the protected page leaked to the signed-out visitor.
    await expect(
        page.getByRole('heading', { name: 'Osoby', exact: true }),
    ).toBeHidden();

    // The Inertia form drops input typed before it hydrates; signing in twice
    // changes nothing, so retry fill + submit until it leaves /login.
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

    await expect(page).toHaveURL(/\/persons-redcheck$/);
    await expect(
        page.getByRole('heading', { name: 'Osoby', level: 1, exact: true }),
    ).toBeVisible();

    // No cleanup: the test creates no data. It does not sign out — the saved
    // session of the shared test user must stay valid for other specs.
});
