// Gate screenshots for context/changes/tree-lines-union-node (Phase 3: family-sheet lines and parent nodes).
// Structure follows context/archive/2026-10-12-design-tokens/screenshots/shots.mjs.
// Run: node shots.mjs <out-dir>
// Needs the app on SHOTS_BASE (default http://localhost:8000) seeded with the demo tree
// (`sail artisan db:seed --class=TreeViewDemoSeeder`, tree-demo@example.com). Playwright comes from the repo.
// deviceScaleFactor 2: at DPR 1 the browser rounds the 1.5 px tree line to 1 px, so the shot would not show the designed weight.
// Output: 4 tree shots x 2 themes (NN-<name>-<theme>.png).
import fs from 'node:fs';
import { chromium } from '@playwright/test';

const base = process.env.SHOTS_BASE ?? 'http://localhost:8000';
const out = process.argv[2];
if (!out) {
    throw new Error('Usage: node shots.mjs <out-dir>');
}
fs.mkdirSync(out, { recursive: true });

const VIEWPORT = { width: 1440, height: 900 };
const demo = 'tree-demo@example.com';
const tmpDir = process.env.CLAUDE_JOB_DIR ? `${process.env.CLAUDE_JOB_DIR}/tmp` : '/tmp';
fs.mkdirSync(tmpDir, { recursive: true });
const authState = `${tmpDir}/tree-lines-auth-state.json`;

// Person ids in the demo tree, in seeder creation order: Jan (proband) 122, Anna 129, Ewa 136,
// Kacper (no unions) 139, Robert Nowicki (five children) 146.
const shots = [
    ['proband-anna', '/tree/122?spouse=129'],
    ['proband-ewa', '/tree/122?spouse=136'],
    ['kacper-no-unions', '/tree/139'],
    ['nowicki-five-children', '/tree/146'],
];
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
        viewport: VIEWPORT,
        deviceScaleFactor: 2,
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

/** Waits until no CSS animation or transition runs (the panel slide, the button ring). */
async function settle(page) {
    await page.waitForFunction(() => document.getAnimations().every((a) => a.playState !== 'running'));
}

let counter = 0;
const num = () => String(++counter).padStart(2, '0');

for (const [name, path] of shots) {
    for (const theme of themes) {
        await run(theme, async (page) => {
            await page.goto(`${base}${path}`);
            await page.waitForLoadState('networkidle');
            await settle(page);
            const file = `${out}/${num()}-${name}-${theme}.png`;
            await page.screenshot({ path: file, fullPage: false });
            console.log(file);
        });
    }
}

await browser.close();
