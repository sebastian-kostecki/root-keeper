// "After" gate screenshots for tree-add-person-panel: the same shots as before/01–08, 05–07 as the add panel.
// Structure follows context/archive/2026-10-12-places-page/screenshots/shots.mjs.
// Run: node shots.mjs <out-dir> <person-id>
//   <person-id>: a person of tree-demo@example.com with no parents and no children (e.g. Wiktor Skanowski).
// Needs the app on SHOTS_BASE (default 127.0.0.1:8100: scripts/e2e-server.sh, DB `e2e`) seeded with
// TreeViewDemoSeeder: `tree-demo-empty@example.com` gives the empty tree (01, 02), `tree-demo@example.com`
// the sheet and the panels (03–08). Opening the add panel saves nothing, so the demo data stays as seeded.
import fs from 'node:fs';
import { chromium } from '@playwright/test';

const base = process.env.SHOTS_BASE ?? 'http://127.0.0.1:8100';
const out = process.argv[2];
const personId = process.argv[3];
if (!out || !personId) {
    throw new Error('Usage: node shots.mjs <out-dir> <person-id>');
}
fs.mkdirSync(out, { recursive: true });

/** @type {{ width: number, height: number }} */
const DESKTOP = { width: 1440, height: 900 };
/** @type {{ width: number, height: number }} */
const MOBILE = { width: 390, height: 844 };
const tmpDir = process.env.CLAUDE_JOB_DIR ? `${process.env.CLAUDE_JOB_DIR}/tmp` : '/tmp';
fs.mkdirSync(tmpDir, { recursive: true });

const browser = await chromium.launch();

/** Logs in once per account (Fortify throttles repeated logins) and returns the stored session state. */
async function login(email) {
    const authState = `${tmpDir}/tree-add-person-panel-${email}.json`;
    if (!fs.existsSync(authState)) {
        const context = await browser.newContext();
        const page = await context.newPage();
        await page.goto(`${base}/login`);
        await page.getByLabel('Adres e-mail').fill(email);
        await page.getByRole('textbox', { name: 'Hasło' }).fill('password');
        await Promise.all([
            page.waitForURL((url) => !url.pathname.startsWith('/login')),
            page.getByRole('button', { name: 'Zaloguj się', exact: true }).click(),
        ]);
        await context.storageState({ path: authState });
        await context.close();
    }

    return authState;
}

async function run(storageState, viewport, fn) {
    const context = await browser.newContext({ viewport, deviceScaleFactor: 1, colorScheme: 'light', storageState });
    const page = await context.newPage();
    try {
        await fn(page);
    } finally {
        await context.close();
    }
}

/** Waits until no CSS animation or transition runs (the panel slide, the canvas pan). */
async function settle(page) {
    await page.waitForFunction(() => document.getAnimations().every((a) => a.playState !== 'running'));
}

async function visit(page, path) {
    await page.goto(`${base}${path}`);
    await page.waitForLoadState('networkidle');
    await settle(page);
}

async function snap(page, name) {
    await page.screenshot({ path: `${out}/${name}.png` });
    console.log(name);
}

/** Whether the element lies fully inside the viewport (the slot or the centre card "in frame"). */
async function inFrame(locator) {
    return locator.evaluate((el) => {
        const r = el.getBoundingClientRect();
        return r.left >= 0 && r.top >= 0 && r.right <= window.innerWidth && r.bottom <= window.innerHeight;
    });
}

const empty = await login('tree-demo-empty@example.com');
const demo = await login('tree-demo@example.com');

await run(empty, DESKTOP, async (page) => {
    await visit(page, '/tree');
    await snap(page, '01-tree-desktop');
});
await run(empty, MOBILE, async (page) => {
    await visit(page, '/tree');
    await snap(page, '02-tree-mobile');
});

let name = '';
await run(demo, DESKTOP, async (page) => {
    await visit(page, `/tree/${personId}`);
    name = ((await page.getByRole('heading', { level: 1 }).textContent()) ?? '').trim();
    await snap(page, '03-sheet-desktop');
});
const slot = (page) => page.getByRole('button', { name: `Dodaj ojca: ${name}`, exact: true });
const addPanel = (page) => page.getByRole('dialog', { name: `Dodaj ojca — ${name}` });

await run(demo, MOBILE, async (page) => {
    await visit(page, `/tree/${personId}`);
    const card = page.getByRole('link', { name: `Podgląd: ${name}` });
    console.log('390 px: centre card in frame', await inFrame(card));
    await snap(page, '04-sheet-mobile');
});

await run(demo, DESKTOP, async (page) => {
    await visit(page, `/tree/${personId}`);
    await slot(page).click();
    await addPanel(page).waitFor({ state: 'visible' });
    await settle(page);
    console.log('panel open: father slot in frame', await inFrame(slot(page)), 'url', page.url());
    await snap(page, '05-add-father-dialog-list');
    await addPanel(page).getByRole('button', { name: 'Nowa osoba' }).click();
    await settle(page);
    await snap(page, '06-add-father-dialog-form');
});

await run(demo, MOBILE, async (page) => {
    await visit(page, `/tree/${personId}`);
    await slot(page).click();
    await addPanel(page).getByRole('button', { name: 'Nowa osoba' }).click();
    await settle(page);
    await snap(page, '07-add-father-form-mobile');
});

await run(demo, DESKTOP, async (page) => {
    await visit(page, `/tree/${personId}`);
    await page.getByRole('link', { name: `Podgląd: ${name}` }).click();
    await page.getByRole('dialog', { name }).waitFor({ state: 'visible' });
    await settle(page);
    console.log('person panel open: father slot in frame', await inFrame(slot(page)));
    await snap(page, '08-person-panel-desktop');
});

await browser.close();
