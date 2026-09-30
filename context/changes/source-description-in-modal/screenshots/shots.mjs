// Gate screenshot set for context/changes/source-description-in-modal (kitchen sink: TreeViewDemoSeeder).
// Structure copied from context/archive/2026-09-29-scans-become-sources/screenshots/shots.mjs.
// Run: node shots.mjs <out-dir> [before|after]
//   before: the source pages as they are before this change (upload is still a page at /sources/create).
//   after:  written in Phase 4, once the modals exist.
// Playwright from the npx cache; not a repo dependency. Out dir is created if missing.
// Ids are read from the rendered lists by file name (original_name), not hard-coded.
// Output: <theme>-<width>-<state>.png
import fs from 'node:fs';
import { chromium } from '/home/sebastian/.npm/_npx/e41f203b7505f1fb/node_modules/playwright/index.mjs';

const base = 'http://localhost:8000';
const out = process.argv[2];
const flavour = process.argv[3] ?? 'after';
if (!out || !['before', 'after'].includes(flavour)) {
    throw new Error('Usage: node shots.mjs <out-dir> [before|after]');
}
if (flavour === 'after') {
    throw new Error('The "after" flavour is written in Phase 4.');
}
fs.mkdirSync(out, { recursive: true });
const browser = await chromium.launch();
const demo = 'tree-demo@example.com';

async function session({ width, height, scheme }) {
    const context = await browser.newContext({ viewport: { width, height }, colorScheme: scheme, deviceScaleFactor: 1 });
    const page = await context.newPage();
    await page.goto(`${base}/login`);
    await page.fill('input[name=email]', demo);
    await page.fill('input[name=password]', 'password');
    await Promise.all([page.waitForURL((u) => !u.pathname.startsWith('/login')), page.click('button[type=submit]')]);
    return { context, page };
}

async function go(page, path) {
    await page.goto(`${base}${path}`);
    await page.waitForLoadState('networkidle');
}

async function snap(page, prefix, name) {
    await page.waitForTimeout(350);
    await page.screenshot({ path: `${out}/${prefix}-${name}.png`, fullPage: false });
    console.log(`${prefix}-${name}`);
}

async function sourceId(page, needle) {
    await go(page, '/sources');
    const id = await page.evaluate((n) => {
        const link = [...document.querySelectorAll('a[href*="/sources/"]')].find((a) => a.textContent.includes(n));
        return link ? Number(link.getAttribute('href').split('/').pop()) : null;
    }, needle);
    if (!id) {
        throw new Error(`No source "${needle}" on /sources`);
    }
    return id;
}

async function personId(page, name) {
    await go(page, '/persons');
    const id = await page.evaluate((needle) => {
        const link = [...document.querySelectorAll('a[href*="person="]')].find((a) => a.textContent.includes(needle));
        return link ? Number(new URL(link.href).searchParams.get('person')) : null;
    }, name);
    if (!id) {
        throw new Error(`No person "${name}" on /persons`);
    }
    return id;
}

async function shoot(page, prefix, ids) {
    await go(page, '/sources');
    await snap(page, prefix, 'list');

    await go(page, '/sources/create');
    await snap(page, prefix, 'upload');
    await page.getByRole('button', { name: 'Zapisz', exact: true }).click();
    await page.waitForLoadState('networkidle');
    await snap(page, prefix, 'upload-kind-error');

    await go(page, `/sources/${ids.gravestone}`);
    await snap(page, prefix, 'preview-unlinked');

    await go(page, `/sources/${ids.linked}`);
    await snap(page, prefix, 'preview-akt');

    await go(page, `/persons?person=${ids.jan}`);
    await page.waitForSelector('[role=dialog]');
    await page.getByRole('tab', { name: /Źródła/ }).click();
    await snap(page, prefix, 'person-sources-tab');
}

const probe = await session({ width: 1280, height: 900, scheme: 'light' });
const ids = {
    jan: await personId(probe.page, 'Jan Kowalski'),
    linked: await sourceId(probe.page, 'akt-wspolny-jan-kacper'),
    gravestone: await sourceId(probe.page, 'nagrobek-kowalskich'),
};
await probe.context.close();
console.log('ids', ids);

for (const scheme of ['light', 'dark']) {
    for (const [width, height] of [
        [1280, 900],
        [390, 844],
    ]) {
        const { context, page } = await session({ width, height, scheme });
        await shoot(page, `${scheme}-${width}`, ids);
        await context.close();
    }
}

await browser.close();
