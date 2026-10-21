// Gate screenshots for context/changes/locality-details (plan Phase 2 §6, Progress 2.9).
// Structure follows context/archive/2026-10-12-places-page/screenshots/shots.mjs.
// Run: node shots.mjs <out-dir>
// Needs the app on SHOTS_BASE (default 127.0.0.1:8100: scripts/e2e-server.sh, DB `e2e`) seeded with
// TreeViewDemoSeeder (`tree-demo@example.com`). Playwright comes from the repo (@playwright/test).
// The script adds its own defunct locality (unique name), shoots three views — the /places list with the
// Rodzaj column and the „nie istnieje” badge, the locality panel with its status and map link, and the
// birth-place suggestion with the „nie istnieje” hint — in light and dark, 1440 and 375 px, then deletes it.
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
const authState = `${tmpDir}/locality-details-auth-state.json`;
const name = `Stare Zalesie ${Date.now().toString().slice(-5)}`;

const browser = await chromium.launch();

// Log in once (Fortify throttles repeated logins), then reuse the session state for every context.
if (!fs.existsSync(authState)) {
    const loginContext = await browser.newContext();
    const loginPage = await loginContext.newPage();
    await loginPage.goto(`${base}/login`);
    await loginPage.getByLabel('Adres e-mail').fill(demo);
    await loginPage.getByRole('textbox', { name: 'Hasło' }).fill('password');
    await Promise.all([
        loginPage.waitForURL((url) => !url.pathname.startsWith('/login')),
        loginPage.getByRole('button', { name: 'Zaloguj się', exact: true }).click(),
    ]);
    await loginContext.storageState({ path: authState });
    await loginContext.close();
}

async function run(viewport, colorScheme, fn) {
    const context = await browser.newContext({
        viewport,
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

/** Waits until no CSS animation or transition runs (the panel slide, the focus ring). */
async function settle(page) {
    await page.waitForFunction(() => document.getAnimations().every((a) => a.playState !== 'running'));
}

async function snap(page, file, fullPage = false) {
    await settle(page);
    await page.screenshot({ path: `${out}/${file}.png`, fullPage });
    console.log(file);
}

// Setup: a defunct village with coordinates and a historical affiliation, added through the panel.
let localityId = '';
await run(DESKTOP, 'light', async (page) => {
    await visit(page, '/places');
    await page.getByRole('link', { name: /^Dodaj miejscowość/ }).click();
    await page.getByLabel('Nazwa').fill(name);
    await page.getByRole('combobox', { name: 'Rodzaj' }).click();
    await page.getByRole('option', { name: 'wieś' }).click();
    await page.getByLabel('Współrzędne').fill('49.9061, 20.4547');
    await page.getByLabel('Przynależność historyczna').fill('gm. Lipnica, pow. bocheński, Galicja');
    await page.getByRole('checkbox', { name: 'Nie istnieje dziś' }).click();
    await page.getByLabel('Co się stało').fill('zajęta pod zalew 1975');
    await page.getByRole('button', { name: 'Zapisz miejscowość' }).click();
    await page.waitForURL(/locality=\d+/);
    localityId = new URL(page.url()).searchParams.get('locality') ?? '';
    console.log('created', name, 'id', localityId);
});

const viewports = [
    { size: DESKTOP, label: 'desktop' },
    { size: MOBILE, label: 'mobile' },
];
const schemes = ['light', 'dark'];
let counter = 0;
const num = () => String(++counter).padStart(2, '0');

try {
    for (const scheme of schemes) {
        for (const { size, label } of viewports) {
            // List: Rodzaj column and the badge beside the name in the sticky cell.
            await run(size, scheme, async (page) => {
                await visit(page, '/places');
                const row = page.getByRole('row').filter({ hasText: name });
                await row.getByText('nie istnieje').waitFor({ state: 'visible' });
                console.log(`list ${scheme} ${label}: row`, JSON.stringify(await row.innerText()));
                await snap(page, `${num()}-list-${scheme}-${label}`, true);
            });

            // Panel: status above the form, all detail fields and „Pokaż na mapie”.
            await run(size, scheme, async (page) => {
                await visit(page, `/places?locality=${localityId}`);
                const map = page.getByRole('link', { name: 'Pokaż na mapie' });
                await map.waitFor({ state: 'visible' });
                console.log(`panel ${scheme} ${label}: map`, await map.getAttribute('href'));
                await snap(page, `${num()}-panel-${scheme}-${label}`);
            });

            // Suggestion: the birth place of a new person offers the locality with the hint.
            await run(size, scheme, async (page) => {
                await visit(page, '/persons?add=person');
                await page.getByLabel('Miejsce').first().fill(name.slice(0, 12));
                const option = page.getByRole('option', { name: `${name} nie istnieje` });
                await option.waitFor({ state: 'visible' });
                console.log(`suggest ${scheme} ${label}: option`, JSON.stringify(await option.innerText()));
                await snap(page, `${num()}-suggest-${scheme}-${label}`);
            });
        }
    }
} finally {
    // Cleanup: the locality is unused, so the panel deletes it after the confirmation.
    await run(DESKTOP, 'light', async (page) => {
        await visit(page, `/places?locality=${localityId}`);
        await page.getByRole('button', { name: 'Usuń miejscowość' }).click();
        await page.getByRole('dialog').getByRole('button', { name: 'Usuń miejscowość' }).click();
        await page.waitForURL((url) => !url.searchParams.has('locality'));
        console.log('deleted', name);
    });
    await browser.close();
}
