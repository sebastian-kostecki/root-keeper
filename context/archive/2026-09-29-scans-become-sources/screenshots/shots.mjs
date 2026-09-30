// Gate screenshot set for context/changes/scans-become-sources (kitchen sink: TreeViewDemoSeeder).
// Structure copied from context/archive/2026-09-29-person-modal/screenshots/shots.mjs.
// Run: node shots.mjs <out-dir> [before]
//   after  (default): this branch, /sources URLs and the new copy.
//   before: run against a checkout of `main` (old /scans URLs, "skan" copy), seeded with the main-branch
//           TreeViewDemoSeeder. Same states where they exist there (no kind, no short delete).
// Playwright from the npx cache; not a repo dependency. Out dir is created if missing.
// Ids are read from the rendered lists by name, not hard-coded. Output: <theme>-<width>-<state>.png
import fs from 'node:fs';
import { chromium } from '/home/sebastian/.npm/_npx/e41f203b7505f1fb/node_modules/playwright/index.mjs';

const base = 'http://localhost:8000';
const out = process.argv[2];
const flavour = process.argv[3] ?? 'after';
if (!out || !['before', 'after'].includes(flavour)) {
    throw new Error('Usage: node shots.mjs <out-dir> [before|after]');
}
const before = flavour === 'before';
const root = before ? 'scans' : 'sources';
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
    await go(page, `/${root}`);
    const id = await page.evaluate(
        ([r, n]) => {
            const link = [...document.querySelectorAll(`a[href*="/${r}/"]`)].find((a) => a.textContent.includes(n));
            return link ? Number(link.getAttribute('href').split('/').pop()) : null;
        },
        [root, needle],
    );
    if (!id) {
        throw new Error(`No source "${needle}" on /${root}`);
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

// State names are shared by both flavours so before/after pair up 1:1 where the state exists in both.
async function shoot(page, prefix, ids) {
    await go(page, `/${root}`);
    await snap(page, prefix, 'list');

    await go(page, `/${root}/create`);
    if (!before) {
        await page.getByRole('button', { name: 'Zapisz', exact: true }).click();
        await page.waitForLoadState('networkidle');
        await snap(page, prefix, 'upload-kind-error');
    } else {
        await snap(page, prefix, 'upload');
    }

    if (!before) {
        await go(page, `/${root}/${ids.gravestone}`);
        await snap(page, prefix, 'preview-gravestone');
        await page.getByRole('button', { name: 'Usuń', exact: true }).click();
        await page.waitForSelector('[role=dialog]');
        await snap(page, prefix, 'delete-short');
    } else {
        await go(page, `/${root}/${ids.linked}`);
        await snap(page, prefix, 'preview-akt');
    }

    await go(page, `/${root}/${ids.linked}`);
    await page.getByRole('button', { name: 'Usuń', exact: true }).click();
    await page.waitForSelector('[role=dialog]');
    await snap(page, prefix, before ? 'delete-linked' : 'delete-linked-both-suffixes');

    await go(page, `/persons?person=${ids.jan}`);
    await page.waitForSelector('[role=dialog]');
    await page.getByRole('tab', { name: /Źródła/ }).click();
    await snap(page, prefix, 'person-sources-tab');
    await page
        .getByRole('button', { name: before ? /Powiąż (kolejny )?skan/ : /Powiąż (kolejny )?akt/ })
        .first()
        .click();
    await page.waitForSelector('[role=dialog] >> text=/Powiąż|Wybierz/');
    await page.waitForLoadState('networkidle');
    await snap(page, prefix, before ? 'picker' : 'picker-akt-only');
}

const probe = await session({ width: 1280, height: 900, scheme: 'light' });
const ids = {
    jan: await personId(probe.page, 'Jan Kowalski'),
    linked: await sourceId(probe.page, before ? 'akt-urodzenia-jan' : 'akt-wspolny-jan-kacper'),
};
if (!before) {
    ids.gravestone = await sourceId(probe.page, 'nagrobek-kowalskich');
    ids.other = await sourceId(probe.page, 'list-rodzinny');
}
await probe.context.close();
console.log('ids', ids);

for (const scheme of ['light', 'dark']) {
    for (const [width, height] of [
        [1280, 900],
        [390, 844],
    ]) {
        const { context, page } = await session({ width, height, scheme });
        await shoot(page, `${scheme}-${width}`, ids);
        if (!before) {
            // "other" kind: the second short-delete variant, with its badge on the preview.
            await go(page, `/${root}/${ids.other}`);
            await snap(page, `${scheme}-${width}`, 'preview-other');
        }
        await context.close();
    }
}

await browser.close();
