// Gate screenshots for context/changes/persons-index-ui (Phase 1 "before" set and later "after" set).
// Structure follows context/archive/2026-09-29-persons-list-views/screenshots/shots.mjs.
// Run: node shots.mjs <out-dir>
// Needs the E2E server on 127.0.0.1:8100 (scripts/e2e-server.sh, DB `e2e`) seeded with
// TreeViewDemoSeeder (see the change's Progress notes). Playwright comes from the repo (@playwright/test).
import fs from 'node:fs';
import os from 'node:os';
import { chromium } from '@playwright/test';

const base = 'http://127.0.0.1:8100';
const out = process.argv[2];
if (!out) {
    throw new Error('Usage: node shots.mjs <out-dir>');
}
fs.mkdirSync(out, { recursive: true });

const DESKTOP = { width: 1440, height: 900 };
const MOBILE = { width: 375, height: 812 };
const demo = 'tree-demo@example.com';

const browser = await chromium.launch();

// Log in once (Fortify throttles repeated logins), then reuse the session state for every context.
const authState = `${os.tmpdir()}/persons-index-ui-auth-state.json`;
if (!fs.existsSync(authState)) {
    const loginContext = await browser.newContext();
    const loginPage = await loginContext.newPage();
    await loginPage.goto(`${base}/login`);
    await loginPage.fill('input[name=email]', demo);
    await loginPage.fill('input[name=password]', 'password');
    await Promise.all([
        loginPage.waitForURL((url) => !url.pathname.startsWith('/login')),
        loginPage.click('button[type=submit]'),
    ]);
    await loginContext.storageState({ path: authState });
    await loginContext.close();
}

async function session(viewport) {
    const context = await browser.newContext({
        viewport,
        deviceScaleFactor: 1,
        colorScheme: 'light',
        storageState: authState,
    });
    const page = await context.newPage();
    return { context, page };
}

async function visit(page, query = '') {
    await page.goto(`${base}/persons${query}`);
    await page.waitForLoadState('networkidle');
}

async function snap(page, name, fullPage = false) {
    await page.screenshot({ path: `${out}/${name}.png`, fullPage });
    console.log(name);
}

async function run(name, viewport, fn) {
    const { context, page } = await session(viewport);
    try {
        await fn(page);
    } finally {
        await context.close();
    }
    return name;
}

// 01-02: list view (View A).
await run('01-list-desktop', DESKTOP, async (page) => {
    await visit(page);
    await snap(page, '01-list-desktop', true);
});
await run('02-list-mobile', MOBILE, async (page) => {
    await visit(page);
    await snap(page, '02-list-mobile', true);
});

// 03-04: table view (View B).
await run('03-table-desktop', DESKTOP, async (page) => {
    await visit(page, '?view=tabela');
    await snap(page, '03-table-desktop', true);
});
await run('04-table-mobile', MOBILE, async (page) => {
    await visit(page, '?view=tabela');
    await snap(page, '04-table-mobile', true);
});

// 05: no results.
await run('05-no-results-desktop', DESKTOP, async (page) => {
    await visit(page, '?q=zzzzzz');
    await snap(page, '05-no-results-desktop');
});

// 06-07: create panel (Dodaj osobę, ?add=person) on desktop and mobile.
for (const [name, viewport] of [
    ['06-create-modal-desktop', DESKTOP],
    ['07-create-modal-mobile', MOBILE],
]) {
    await run(name, viewport, async (page) => {
        await visit(page);
        await page.getByRole('link', { name: /dodaj osobę/i }).first().click();
        await page.getByRole('dialog', { name: 'Dodaj osobę' }).waitFor({ state: 'visible' });
        await snap(page, name);
    });
}

// 08: keyboard focus on a letter jump link (the hand-written focus ring).
await run('08-focus-jump-link', DESKTOP, async (page) => {
    await visit(page);
    const jump = page.getByRole('navigation', { name: 'Przejdź do litery' }).getByRole('link').first();
    await jump.focus();
    const focusVisible = await jump.evaluate((el) => el.matches(':focus-visible'));
    console.log('jump link :focus-visible =', focusVisible);
    await snap(page, '08-focus-jump-link');
});

// 09: keyboard focus on a table row link (the hand-written focus ring).
await run('09-focus-table-row', DESKTOP, async (page) => {
    await visit(page, '?view=tabela');
    const row = page.locator('tbody a[href*="person="]').first();
    await row.focus();
    const focusVisible = await row.evaluate((el) => el.matches(':focus-visible'));
    console.log('table row link :focus-visible =', focusVisible);
    await snap(page, '09-focus-table-row');
});

// 10: a search whose request is cut off (the same approach as persons-add-person-panel.spec.ts).
await run('10-search-error-desktop', DESKTOP, async (page) => {
    await visit(page);
    await page.route(
        (url) => url.pathname === '/persons' && url.searchParams.has('q'),
        (route) => (route.request().headers()['x-inertia'] ? route.abort() : route.continue()),
    );
    await page.getByRole('searchbox', { name: 'Szukaj osoby' }).fill('zzzzzz');
    await page.getByRole('alert').waitFor({ state: 'visible' });
    await snap(page, '10-search-error-desktop');
});

await browser.close();
