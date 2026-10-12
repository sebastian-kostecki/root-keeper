// Gate screenshots for context/changes/sources-places-polish ("before" before the first view edit, "after" once Phase 3 lands).
// Structure follows context/archive/2026-10-12-design-tokens/screenshots/shots.mjs.
// Run: node shots.mjs <out-dir>
// Needs the app on SHOTS_BASE (default http://localhost:8000, Sail dev server) seeded with TreeViewDemoSeeder:
// tree-demo@example.com (full tree) and tree-demo-empty@example.com (empty tree, for empty states).
// Missing demo states (no thumbnail, no panel target) are logged and skipped, never fatal.
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
const demoEmpty = 'tree-demo-empty@example.com';
const tmpDir = process.env.CLAUDE_JOB_DIR ? `${process.env.CLAUDE_JOB_DIR}/tmp` : '/tmp';
fs.mkdirSync(tmpDir, { recursive: true });
const authState = `${tmpDir}/sources-places-auth-state.json`;
const emptyAuthState = `${tmpDir}/sources-places-empty-auth-state.json`;

const themes = ['light', 'dark'];

const browser = await chromium.launch();

async function login(email, statePath) {
    if (fs.existsSync(statePath)) {
        return;
    }
    const loginContext = await browser.newContext();
    const loginPage = await loginContext.newPage();
    await loginPage.goto(`${base}/login`);
    await loginPage.fill('input[name=email]', email);
    await loginPage.fill('input[name=password]', 'password');
    await Promise.all([
        loginPage.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 15000 }),
        loginPage.click('button[type=submit]'),
    ]);
    await loginContext.storageState({ path: statePath });
    await loginContext.close();
}

// Log in once per account (Fortify throttles repeated logins), then reuse the session state.
await login(demo, authState);
await login(demoEmpty, emptyAuthState);

async function run(colorScheme, statePath, fn) {
    const context = await browser.newContext({
        viewport: DESKTOP,
        deviceScaleFactor: 1,
        colorScheme,
        storageState: statePath,
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

/** Clicks the first matching locator when present; logs and returns false otherwise. */
async function clickFirst(page, locator, label) {
    const target = locator.first();
    if ((await target.count()) === 0) {
        console.log(`skip ${label}: no target`);
        return false;
    }
    await target.click();
    // Side panels are role="dialog"; wait for it so the shot is taken with the panel open.
    const opened = await page
        .getByRole('dialog')
        .first()
        .waitFor({ state: 'visible', timeout: 10000 })
        .then(() => true)
        .catch(() => false);
    if (!opened) {
        console.log(`skip ${label}: panel did not open`);
        return false;
    }
    await settle(page);
    return true;
}

let counter = 0;
const num = () => String(++counter).padStart(2, '0');

// Lists and pages, both themes.
const pageShots = [
    { name: 'sources', path: '/sources', state: authState },
    { name: 'places-localities', path: '/places', state: authState },
    { name: 'places-parishes', path: '/places?kind=parafie', state: authState },
    { name: 'persons', path: '/persons', state: authState },
    { name: 'settings-profile', path: '/settings/profile', state: authState },
    { name: 'settings-security', path: '/settings/security', state: authState },
    { name: 'settings-appearance', path: '/settings/appearance', state: authState },
    { name: 'sources-empty', path: '/sources', state: emptyAuthState },
    { name: 'places-empty', path: '/places', state: emptyAuthState },
    { name: 'places-parishes-empty', path: '/places?kind=parafie', state: emptyAuthState },
];

for (const theme of themes) {
    for (const shot of pageShots) {
        await run(theme, shot.state, async (page) => {
            await visit(page, shot.path);
            await settle(page);
            await snap(page, `${num()}-${shot.name}-${theme}`, true);
        });
    }
}

// Open panels (source, locality, parish) and the places search without results.
for (const theme of themes) {
    await run(theme, authState, async (page) => {
        await visit(page, '/sources');
        if (await clickFirst(page, page.locator('main a[href*="/sources"]'), 'source panel')) {
            await snap(page, `${num()}-source-panel-${theme}`);
        }
    });

    await run(theme, authState, async (page) => {
        await visit(page, '/places');
        if (await clickFirst(page, page.locator('main tbody a'), 'locality panel')) {
            await snap(page, `${num()}-locality-panel-${theme}`);
        }
    });

    await run(theme, authState, async (page) => {
        await visit(page, '/places?kind=parafie');
        if (await clickFirst(page, page.locator('main tbody a'), 'parish panel')) {
            await snap(page, `${num()}-parish-panel-${theme}`);
        }
    });

    await run(theme, authState, async (page) => {
        await visit(page, '/places');
        const search = page.getByRole('searchbox').first();
        if ((await search.count()) === 0) {
            console.log('skip places search: no searchbox');
            return;
        }
        await search.fill('zzz-brak-wynikow-2026');
        await page.waitForLoadState('networkidle');
        await settle(page);
        await snap(page, `${num()}-places-no-results-${theme}`, true);
    });
}

// Keyboard focus on the first gallery card of /sources, logging boxShadow and outline.
for (const theme of themes) {
    await run(theme, authState, async (page) => {
        await visit(page, '/sources');
        if ((await page.locator('main ul.grid > li > a').count()) === 0) {
            console.log(`sources focus ${theme}: no card link`);
            return;
        }
        let found = false;
        for (let i = 0; i < 80 && !found; i += 1) {
            await page.keyboard.press('Tab');
            found = await page.evaluate(() => {
                const el = document.activeElement;
                return !!el && el.tagName === 'A' && !!el.closest('main ul.grid > li');
            });
        }
        if (!found) {
            console.log(`sources focus ${theme}: no card link reached by Tab`);
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
        console.log(`sources focus-card ${theme}`, JSON.stringify(focus));
        await snap(page, `${num()}-sources-focus-card-${theme}`);
    });
}

// Person with a confirmed fact and one without a source (chip states), both themes.
for (const theme of themes) {
    await run(theme, authState, async (page) => {
        await visit(page, '/persons');
        await page.getByText('Tabela', { exact: true }).click();
        await page.locator('tbody a').first().waitFor({ state: 'visible' });
        if (await clickFirst(page, page.locator('main tbody a').first(), 'person panel')) {
            await snap(page, `${num()}-person-panel-${theme}`);
        }
    });
}

await browser.close();
