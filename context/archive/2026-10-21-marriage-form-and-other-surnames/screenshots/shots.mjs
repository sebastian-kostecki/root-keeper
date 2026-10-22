// Gate screenshots for context/changes/marriage-form-and-other-surnames (Phase 2: wedding form in the "Ślub" card and the Family row).
// Run: node shots.mjs <out-dir>
// Needs the dev app on SHOTS_BASE (default http://127.0.0.1:8000) seeded with the demo tree
// (`sail artisan db:seed --class=TreeViewDemoSeeder`, tree-demo@example.com). Playwright comes from the repo.
// IDs: SHOTS_PERSON = Jan, SHOTS_KNOWN = Jan + Anna (cywilno-wyznaniowy), SHOTS_UNKNOWN = Jan + Ewa (form unknown).
// Output: NN-<name>-<viewport>-<theme>.png, deviceScaleFactor 2, light + dark at 1366x768, light at 390x844.
// The edit shot only opens the card's edit mode; nothing is saved.
import fs from 'node:fs';
import { chromium } from '@playwright/test';

const base = process.env.SHOTS_BASE ?? 'http://127.0.0.1:8000';
const out = process.argv[2];
if (!out) {
    throw new Error('Usage: node shots.mjs <out-dir>');
}
fs.mkdirSync(out, { recursive: true });

const demo = 'tree-demo@example.com';
const tmpDir = process.env.CLAUDE_JOB_DIR ? `${process.env.CLAUDE_JOB_DIR}/tmp` : '/tmp';
fs.mkdirSync(tmpDir, { recursive: true });
const authState = `${tmpDir}/marriage-form-auth.json`;

const PERSON = Number(process.env.SHOTS_PERSON ?? 692);
const KNOWN = Number(process.env.SHOTS_KNOWN ?? 1218);
const UNKNOWN = Number(process.env.SHOTS_UNKNOWN ?? 1219);
const runs = [
    { name: 'desktop', size: { width: 1366, height: 768 }, theme: 'light' },
    { name: 'desktop', size: { width: 1366, height: 768 }, theme: 'dark' },
    { name: 'phone', size: { width: 390, height: 844 }, theme: 'light' },
];

const browser = await chromium.launch();

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

async function settle(page) {
    await page.waitForLoadState('networkidle');
    await page.waitForFunction(() => document.getAnimations().every((a) => a.playState !== 'running'));
}

async function openPanel(page, query) {
    await page.goto(`${base}/tree/${PERSON}?${query}`);
    const panel = page.getByRole('dialog').first();
    await panel.waitFor();
    await settle(page);

    return panel;
}

let counter = 0;
const num = () => String(++counter).padStart(2, '0');

for (const { name, size, theme } of runs) {
    const context = await browser.newContext({
        viewport: size,
        deviceScaleFactor: 2,
        colorScheme: theme,
        storageState: authState,
    });
    const page = await context.newPage();
    const shot = (label) => page.screenshot({ path: `${out}/${num()}-${label}-${name}-${theme}.png` });
    try {
        let panel = await openPanel(page, `marriage=${KNOWN}`);
        await shot('marriage-view-known');

        await panel.getByRole('button', { name: 'Edytuj Ślub' }).click();
        await panel.getByLabel('Forma ślubu').waitFor();
        await settle(page);
        await shot('marriage-edit');

        await openPanel(page, `marriage=${UNKNOWN}`);
        await shot('marriage-view-unknown');

        panel = await openPanel(page, `person=${PERSON}`);
        await panel.getByRole('tab', { name: 'Rodzina' }).click();
        await settle(page);
        await shot('family-row');

        const overflow = await page.evaluate(
            () => document.documentElement.scrollWidth > document.documentElement.clientWidth,
        );
        console.log(`${name}-${theme}: horizontal overflow = ${overflow}`);
    } finally {
        await context.close();
    }
}

await browser.close();
