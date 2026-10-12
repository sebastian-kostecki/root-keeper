// Gate screenshots for context/changes/places-page ("before" set now, "after" set once the merged page exists).
// Structure follows context/archive/2026-10-12-sources-page/screenshots/shots.mjs.
// Run: node shots.mjs <out-dir>
// Needs the E2E server on 127.0.0.1:8100 (scripts/e2e-server.sh, DB `e2e`) seeded with
// TreeViewDemoSeeder (`tree-demo@example.com`). Playwright comes from the repo (@playwright/test).
// Besides the PNGs, the script logs the measurements the audit needs (:focus-visible on a row link,
// heading row wrap on 375 px, sidebar entries).
import fs from 'node:fs';
import { chromium } from '@playwright/test';

const base = process.env.SHOTS_BASE ?? 'http://127.0.0.1:8100';
const out = process.argv[2];
if (!out) {
    throw new Error('Usage: node shots.mjs <out-dir>');
}
fs.mkdirSync(out, { recursive: true });

const DESKTOP = { width: 1440, height: 900 };
const MOBILE = { width: 375, height: 812 };
const demo = 'tree-demo@example.com';
const tmpDir = process.env.CLAUDE_JOB_DIR ? `${process.env.CLAUDE_JOB_DIR}/tmp` : '/tmp';
fs.mkdirSync(tmpDir, { recursive: true });
const authState = `${tmpDir}/places-page-auth-state.json`;

const browser = await chromium.launch();

// Log in once (Fortify throttles repeated logins), then reuse the session state for every context.
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

async function run(viewport, fn) {
    const context = await browser.newContext({
        viewport,
        deviceScaleFactor: 1,
        colorScheme: 'light',
        storageState: authState,
    });
    const page = await context.newPage();
    try {
        await fn(page);
    } finally {
        await context.close();
    }
}

async function visit(page, path) {
    await page.goto(`${base}${path}`);
    await page.waitForLoadState('networkidle');
}

async function snap(page, name, fullPage = false) {
    await page.screenshot({ path: `${out}/${name}.png`, fullPage });
    console.log(name);
}

const lists = [
    { path: '/localities', key: 'locality', slug: 'localities' },
    { path: '/parishes', key: 'parish', slug: 'parishes' },
];

for (const [index, list] of lists.entries()) {
    const n = index * 4;
    const id = (k) => String(n + k).padStart(2, '0');

    await run(DESKTOP, async (page) => {
        await visit(page, list.path);
        const nav = await page
            .getByRole('navigation')
            .first()
            .getByRole('link')
            .allTextContents()
            .catch(() => []);
        console.log(`${list.slug} sidebar links`, JSON.stringify(nav));
        await snap(page, `${id(1)}-${list.slug}-desktop`, true);
    });

    await run(MOBILE, async (page) => {
        await visit(page, list.path);
        const heading = await page.getByRole('heading', { level: 1 }).boundingBox();
        const add = await page.getByRole('link', { name: /^Dodaj/ }).boundingBox();
        console.log(`${list.slug} 375 px: heading`, JSON.stringify(heading), 'add', JSON.stringify(add));
        await snap(page, `${id(2)}-${list.slug}-mobile`, true);
    });

    // Open the first record: panel beside the list, selected row.
    await run(DESKTOP, async (page) => {
        await visit(page, list.path);
        await page.locator(`tbody a[href*="${list.key}="]`).first().click();
        await page.waitForURL(new RegExp(`${list.key}=\\d+`));
        await page.locator('tbody tr[data-state="selected"]').waitFor({ state: 'visible' });
        // The panel slides in: wait until no animation is running before the shot.
        await page.waitForFunction(() => document.getAnimations().every((a) => a.playState !== 'running'));
        await snap(page, `${id(3)}-${list.slug}-panel-desktop`);
    });

    // Keyboard focus: the add button first, then the first row link (both hrefs carry the panel key).
    await run(DESKTOP, async (page) => {
        await visit(page, list.path);
        const focusOf = () =>
            page.evaluate(() => {
                const el = document.activeElement;
                return {
                    text: el?.textContent?.trim().slice(0, 40),
                    inTable: !!el?.closest('tbody'),
                    focusVisible: el?.matches(':focus-visible') ?? false,
                    boxShadow: el ? getComputedStyle(el).boxShadow : null,
                    outline: el ? getComputedStyle(el).outlineStyle : null,
                };
            });
        let seen = 0;
        for (let i = 0; i < 60 && seen < 2; i += 1) {
            await page.keyboard.press('Tab');
            const href = await page.evaluate(() => document.activeElement?.getAttribute('href') ?? '');
            if (href.includes(`${list.key}=`)) {
                seen += 1;
                // Button rings come in through `transition-[box-shadow]`: measure once it has finished.
                await page.waitForFunction(() =>
                    document.getAnimations().every((a) => a.playState !== 'running'),
                );
                console.log(`${list.slug} focus #${seen}`, JSON.stringify(await focusOf()));
                if (seen === 1) {
                    await snap(page, `${id(4)}-${list.slug}-focus-add`);
                }
            }
        }
        await snap(page, `${id(4)}-${list.slug}-focus-row`);
    });
}

await browser.close();
