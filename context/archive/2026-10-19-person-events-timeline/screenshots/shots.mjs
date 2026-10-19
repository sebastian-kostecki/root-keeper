// Gate screenshots for context/changes/person-events-timeline (Phase 3: Wydarzenia tab as a timeline).
// Run: node shots.mjs <out-dir>
// Needs the dev app on SHOTS_BASE (default http://localhost:8000) seeded with the demo tree
// (`sail artisan db:seed --class=TreeViewDemoSeeder`, tree-demo@example.com). Playwright comes from the repo.
// Output: NN-<name>-<theme>.png, deviceScaleFactor 2, light + dark, 1366x768 and 390x844.
import fs from 'node:fs';
import { chromium } from '@playwright/test';

const base = process.env.SHOTS_BASE ?? 'http://localhost:8000';
const out = process.argv[2];
if (!out) {
    throw new Error('Usage: node shots.mjs <out-dir>');
}
fs.mkdirSync(out, { recursive: true });

const demo = 'tree-demo@example.com';
const tmpDir = process.env.CLAUDE_JOB_DIR ? `${process.env.CLAUDE_JOB_DIR}/tmp` : '/tmp';
fs.mkdirSync(tmpDir, { recursive: true });
const authState = `${tmpDir}/person-events-timeline-auth.json`;

const PERSON_ID = Number(process.env.SHOTS_PERSON ?? 173);
const tag = process.env.SHOTS_TAG ? `${process.env.SHOTS_TAG}-` : "";
const viewports = [
    { name: 'desktop', size: { width: 1366, height: 768 } },
    { name: 'phone', size: { width: 390, height: 844 } },
];
const themes = ['light', 'dark'];

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
    await page.waitForFunction(() => document.getAnimations().every((a) => a.playState !== 'running'));
}

let counter = 0;
const num = () => String(++counter).padStart(2, '0');

for (const { name: vpName, size: viewport } of viewports) {
    for (const theme of themes) {
        const context = await browser.newContext({
            viewport,
            deviceScaleFactor: 2,
            colorScheme: theme,
            storageState: authState,
        });
        const page = await context.newPage();
        try {
            await page.goto(`${base}/tree/${PERSON_ID}?person=${PERSON_ID}`);
            const panel = page.getByRole('dialog').first();
            await panel.waitFor();
            await panel.getByRole('tab', { name: 'Wydarzenia' }).click();
            await page.waitForLoadState('networkidle');
            await settle(page);

            const overflow = await page.evaluate(
                () => document.documentElement.scrollWidth > document.documentElement.clientWidth,
            );
            console.log(`${vpName}-${theme}: horizontal overflow = ${overflow}`);

            await page.screenshot({ path: `${out}/${num()}-${tag}${vpName}-default-${theme}.png` });

            const link = panel.getByRole('listitem').getByRole('link').first();
            if (await link.count()) {
                await link.hover();
                await settle(page);
                await page.screenshot({ path: `${out}/${num()}-${tag}${vpName}-link-hover-${theme}.png` });

                await page.mouse.move(0, 0);
                await panel.getByRole('tab', { name: 'Wydarzenia' }).focus();
                let guard = 0;
                while (guard++ < 40) {
                    await page.keyboard.press('Tab');
                    if (await link.evaluate((el) => el === document.activeElement)) {
                        break;
                    }
                }
                await settle(page);
                await page.screenshot({ path: `${out}/${num()}-${tag}${vpName}-link-focus-${theme}.png` });
            }
        } finally {
            await context.close();
        }
    }
}

await browser.close();
