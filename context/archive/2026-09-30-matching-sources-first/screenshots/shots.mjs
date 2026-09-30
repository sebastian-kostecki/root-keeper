// Gate screenshot set for context/changes/matching-sources-first (kitchen sink: TreeViewDemoSeeder).
// Structure copied from context/archive/2026-09-30-fact-confirmed-by-any-source/screenshots/shots.mjs.
// Run: LD_LIBRARY_PATH=<nss/nspr/asound libs> node shots.mjs <out-dir> [before|after]
//   Chromium needs LD_LIBRARY_PATH=/tmp/chromelibs/x/usr/lib/x86_64-linux-gnu on this host
//   (bare `node` fails on libnspr4.so).
//   before: shot at the end of Phase 1, before any picker edit (one mixed list, newest first).
//   after:  shot after Phase 2. Both flavours cover the same four states with the same selectors,
//   so before/ and after/ compare one to one.
// States: picker-leon-death (Leon's death, "Przegląd", as opened), picker-leon-death-end (same picker, last tile
//   scrolled into view), picker-jan-birth ("Źródła" of Jan, birth group, as opened), picker-jan-marriage
//   ("Źródła" of Jan, marriage with Anna, as opened).
// Playwright from the npx cache; not a repo dependency. Out dir is created if missing.
// Ids are read from the rendered lists by text, not hard-coded. Each shot waits until the skeleton is gone.
// Output: <theme>-<width>-<state>.png (4 states x 2 themes x 2 widths = 16 PNGs)
import fs from 'node:fs';
import { chromium } from '/home/sebastian/.npm/_npx/e41f203b7505f1fb/node_modules/playwright/index.mjs';

const base = 'http://localhost:8000';
const out = process.argv[2];
const flavour = process.argv[3] ?? 'after';
if (!out || !['before', 'after'].includes(flavour)) {
    throw new Error('Usage: node shots.mjs <out-dir> [before|after]');
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

// The picker is a second, sibling dialog (portalled), not nested in the person modal: it is the last one.
const picker = (page) => page.locator('[role=dialog]').last();

async function pickerLoaded(page) {
    await picker(page).locator('[data-slot=skeleton], .animate-pulse').first().waitFor({ state: 'detached', timeout: 10000 }).catch(() => {});
    await picker(page).locator('button').first().waitFor({ timeout: 10000 });
}

async function snap(page, prefix, name, fullPage = false) {
    await page.waitForTimeout(350);
    await page.screenshot({ path: `${out}/${prefix}-${name}.png`, fullPage });
    console.log(`${prefix}-${name}`);
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

async function closeDialogs(page) {
    await page.keyboard.press('Escape');
    await page.keyboard.press('Escape');
    await page.waitForTimeout(250);
}

async function openPerson(page, id, tab) {
    await go(page, `/persons?person=${id}`);
    await page.waitForSelector('[role=dialog]');
    if (tab !== 'Przegląd') {
        await page.getByRole('tab', { name: new RegExp(tab) }).click();
    }
}

async function shoot(page, prefix, ids) {
    const linkButton = /Powiąż (kolejne )?źródło/;

    // Picker from Leon's death (no source yet): "Powiąż źródło" in the "Zgon" section, as opened, then scrolled to the end.
    await openPerson(page, ids.leon, 'Przegląd');
    await page.getByRole('region', { name: 'Zgon', exact: true }).getByRole('button', { name: linkButton }).click();
    await pickerLoaded(page);
    await snap(page, prefix, 'picker-leon-death');
    await picker(page).locator('.overflow-y-auto button').last().scrollIntoViewIfNeeded();
    await snap(page, prefix, 'picker-leon-death-end');
    await closeDialogs(page);

    // Picker from Jan's birth: first "Powiąż kolejne źródło" on the "Źródła" tab is the birth group.
    await openPerson(page, ids.jan, 'Źródła');
    await page.getByRole('button', { name: linkButton }).first().click();
    await pickerLoaded(page);
    await snap(page, prefix, 'picker-jan-birth');
    await closeDialogs(page);

    // Picker from Jan and Anna's marriage: the "Źródła" group whose heading names Anna.
    await openPerson(page, ids.jan, 'Źródła');
    await page
        .locator('section', { has: page.getByRole('heading', { name: /Małżeństwo z Anna/ }) })
        .getByRole('button', { name: linkButton })
        .click();
    await pickerLoaded(page);
    await snap(page, prefix, 'picker-jan-marriage');
    await closeDialogs(page);
}

const probe = await session({ width: 1280, height: 900, scheme: 'light' });
const ids = {
    jan: await personId(probe.page, 'Jan Kowalski'),
    leon: await personId(probe.page, 'Leon Kowalski'),
};
await probe.context.close();
console.log('flavour', flavour, 'ids', ids);

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
