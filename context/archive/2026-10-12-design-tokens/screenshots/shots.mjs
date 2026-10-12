// Gate screenshots for context/changes/design-tokens (the "before" set before app.css changes, "after" once Phase 4 lands).
// Structure follows context/archive/2026-10-12-places-page/screenshots/shots.mjs.
// Run: node shots.mjs <out-dir>
// Needs the app on SHOTS_BASE (default http://localhost:8000, Sail dev server) seeded with the demo tree
// (`tree-demo@example.com`). Playwright comes from the repo (@playwright/test).
// Output: 8 full-page shots (4 routes x 2 themes, NN-<route>-<theme>.png) and 2 keyboard-focus shots on the
// first row link of /persons (one per theme), with the computed boxShadow / outline logged.
import fs from 'node:fs';
import { chromium } from '@playwright/test';

const base = process.env.SHOTS_BASE ?? 'http://localhost:8000';
const out = process.argv[2];
if (!out) {
    throw new Error('Usage: node shots.mjs <out-dir>');
}
fs.mkdirSync(out, { recursive: true });

const DESKTOP = { width: 1440, height: 900 };
const demo = 'tree-demo@example.com';
const tmpDir = process.env.CLAUDE_JOB_DIR ? `${process.env.CLAUDE_JOB_DIR}/tmp` : '/tmp';
fs.mkdirSync(tmpDir, { recursive: true });
const authState = `${tmpDir}/design-tokens-auth-state.json`;

const routes = ['tree', 'persons', 'sources', 'places'];
const themes = ['light', 'dark'];

const browser = await chromium.launch();

// Log in once (Fortify throttles repeated logins), then reuse the session state for every context.
if (!fs.existsSync(authState)) {
    const loginContext = await browser.newContext();
    const loginPage = await loginContext.newPage();
    await loginPage.goto(`${base}/login`);
    await loginPage.fill('input[name=email]', demo);
    await loginPage.fill('input[name=password]', 'password');
    await Promise.all([
        loginPage.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 15000 }),
        loginPage.click('button[type=submit]'),
    ]);
    await loginContext.storageState({ path: authState });
    await loginContext.close();
}

async function run(colorScheme, fn) {
    const context = await browser.newContext({
        viewport: DESKTOP,
        deviceScaleFactor: 1,
        colorScheme,
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

/** Waits until no CSS animation or transition runs (the panel slide, the button ring). */
async function settle(page) {
    await page.waitForFunction(() => document.getAnimations().every((a) => a.playState !== 'running'));
}

let counter = 0;
const num = () => String(++counter).padStart(2, '0');

for (const route of routes) {
    for (const theme of themes) {
        await run(theme, async (page) => {
            await visit(page, `/${route}`);
            await settle(page);
            await snap(page, `${num()}-${route}-${theme}`, true);
        });
    }
}

// Keyboard focus on the first row link of /persons: Tab until the focus lands inside the table body.
for (const theme of themes) {
    await run(theme, async (page) => {
        await visit(page, '/persons');
        await page.getByText('Tabela', { exact: true }).click();
        await page.locator('tbody a').first().waitFor({ state: 'visible' });
        let found = false;
        for (let i = 0; i < 80 && !found; i += 1) {
            await page.keyboard.press('Tab');
            found = await page.evaluate(() => {
                const el = document.activeElement;
                return !!el && el.tagName === 'A' && !!el.closest('tbody');
            });
        }
        if (!found) {
            console.log(`persons focus ${theme}: no row link reached by Tab`);
            return;
        }
        await settle(page);
        const focus = await page.evaluate(() => {
            const el = document.activeElement;
            const style = getComputedStyle(el);
            return {
                text: el?.textContent?.trim().slice(0, 40),
                focusVisible: el?.matches(':focus-visible') ?? false,
                boxShadow: style.boxShadow,
                outlineStyle: style.outlineStyle,
            };
        });
        console.log(`persons focus-row ${theme}`, JSON.stringify(focus));
        await snap(page, `${num()}-persons-focus-row-${theme}`);
    });
}

await browser.close();
