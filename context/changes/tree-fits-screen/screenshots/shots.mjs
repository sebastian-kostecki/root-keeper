// Gate screenshot set for context/changes/tree-fits-screen (kitchen sink: TreeViewDemoSeeder).
// Pattern: context/archive/2026-09-29-tree-sheet-variant-c/screenshots/shots.mjs. Shots are taken after the
// start fit, viewport only (no fullPage: the page no longer scrolls). There is no manual zoom, so no
// "after Dopasuj" shot. Each shot logs the applied scale and whether the page scrolls vertically.
// Run: node shots.mjs <out-dir>  (Playwright from the npx cache; not a repo dependency; add LD_LIBRARY_PATH
// for nss/nspr/asound libs when Chromium does not start). Out dir is created if missing.
// Person ids are NOT hard-coded: the proband id (Jan) is read from /tree, all others are offsets from it,
// following the creation order in database/seeders/TreeViewDemoSeeder.php (reseeded ids drift).
import fs from 'node:fs';
import { chromium } from '/home/sebastian/.npm/_npx/e41f203b7505f1fb/node_modules/playwright/index.mjs';

const base = 'http://localhost:8000';
const out = process.argv[2];
if (!out) {
    throw new Error('Usage: node shots.mjs <out-dir>');
}
fs.mkdirSync(out, { recursive: true });
const browser = await chromium.launch();
const demo = 'tree-demo@example.com';

// One login for the whole run (the login form is throttled); every session reuses its storage state.
const loginContext = await browser.newContext();
{
    const page = await loginContext.newPage();
    await page.goto(`${base}/login`);
    await page.fill('input[name=email]', demo);
    await page.fill('input[name=password]', 'password');
    await Promise.all([page.waitForURL((u) => !u.pathname.startsWith('/login')), page.click('button[type=submit]')]);
}
const storageState = await loginContext.storageState();
await loginContext.close();

async function session({ width, height, scheme = 'light', collapsed = false }) {
    const context = await browser.newContext({
        viewport: { width, height },
        colorScheme: scheme,
        deviceScaleFactor: 1,
        storageState,
    });
    const page = await context.newPage();
    await page.goto(`${base}/tree`);
    await page.waitForLoadState('networkidle');
    const sidebarOpen = await page.locator('[data-sidebar="trigger"] .sr-only', { hasText: 'Zwiń' }).count();
    if (collapsed && sidebarOpen) {
        await page.locator('[data-sidebar="trigger"]').first().click();
    } else if (!collapsed && !sidebarOpen) {
        await page.locator('[data-sidebar="trigger"]').first().click();
    }
    return { context, page };
}

// Navigate, wait for the start fit (content visible), settle, screenshot the viewport.
async function shot(page, path, name) {
    await page.goto(`${base}${path}`);
    await page.waitForLoadState('networkidle');
    await page.locator('.zoom-canvas-content').waitFor({ state: 'visible' });
    await page.waitForFunction(() => getComputedStyle(document.querySelector('.zoom-canvas-content')).visibility === 'visible');
    await page.evaluate(() => new Promise((r) => requestAnimationFrame(() => requestAnimationFrame(r))));
    await page.waitForTimeout(250); // let the (zero-duration) transform and fonts settle before the pixels
    const info = await page.evaluate(() => {
        const content = document.querySelector('.zoom-canvas-content');
        const wrapper = document.querySelector('.zoom-canvas-wrapper');
        const m = new DOMMatrix(getComputedStyle(content).transform);
        const w = wrapper.getBoundingClientRect();
        const c = content.getBoundingClientRect();
        return {
            scale: Number(m.a.toFixed(3)),
            wrapper: `${Math.round(w.width)}x${Math.round(w.height)}`,
            natural: `${content.offsetWidth}x${content.offsetHeight}`,
            overflowsFrame: c.left < w.left - 1 || c.right > w.right + 1 || c.top < w.top - 1 || c.bottom > w.bottom + 1,
            pageScrolls: document.documentElement.scrollHeight > window.innerHeight,
        };
    });
    await page.screenshot({ path: `${out}/${name}.png`, fullPage: false });
    console.log(name, JSON.stringify(info));
    return info;
}

// Proband id (Jan) from /tree: Inertia page props, fallback to the breadcrumb link.
let jan;
{
    const { context, page } = await session({ width: 1440, height: 900 });
    jan = await page.evaluate(() => {
        const el = document.querySelector('script[data-page]') ?? document.querySelector('[data-page]');
        const raw = el?.textContent?.trim() || el?.getAttribute('data-page');
        try {
            const id = JSON.parse(raw)?.props?.sheet?.focus?.id;
            if (id) return id;
        } catch {}
        const href = document.querySelector('nav[aria-label=breadcrumb] a[href*="/tree/"]')?.getAttribute('href') ?? '';
        const m = href.match(/\/tree\/(\d+)/);
        return m ? Number(m[1]) : null;
    });
    await context.close();
}
if (!jan) {
    throw new Error('Could not derive proband id from /tree');
}
// Offsets from Jan = creation order in TreeViewDemoSeeder (fathers of the couples; mothers are +1).
const couples = {
    '0-children': jan + 22, // Sasza Mazur is unrelated here; use Anna's union below instead (see zero)
    '3-children': jan + 31, // Marek Konieczny
    '5-children': jan + 24, // Robert Nowicki
    '8-children': jan + 41, // Dariusz Ósmy
    '10-children': jan + 51, // Zbigniew Dziesiąty
};
// Zero children: Jan with Ewa-less union is not empty either, so use Ewa's partner view of Jan? Ewa has Michał.
// Kacper (jan + 17) has no unions and no children: the sheet shows "Brak dzieci w tym związku."
couples['0-children'] = jan + 17;
console.log('ids', { jan, ...couples });

const viewports = {
    '1366': { width: 1366, height: 768 },
    '2560': { width: 2560, height: 1440 },
};

// Proband (several unions): 1366 expanded and collapsed menu, 1920, 2560.
{
    let s = await session({ ...viewports['1366'] });
    await shot(s.page, '/tree', 'proband-1366-menu-open');
    await s.context.close();

    s = await session({ ...viewports['1366'], collapsed: true });
    await shot(s.page, '/tree', 'proband-1366-menu-closed');
    await s.context.close();

    s = await session({ width: 1920, height: 1080 });
    await shot(s.page, '/tree', 'proband-1920');
    await s.context.close();

    s = await session({ ...viewports['2560'] });
    await shot(s.page, '/tree', 'proband-2560');
    await s.context.close();
}

// Couples with 0, 3, 5, 8, 10 children at 1366 and 2560.
for (const [label, vp] of Object.entries(viewports)) {
    const { context, page } = await session(vp);
    for (const [kids, father] of Object.entries(couples)) {
        await shot(page, `/tree/${father}`, `couple-${kids}-${label}`);
    }
    await context.close();
}

// Content narrowed like beside a `standard` side panel: ~670 px of canvas, simulated by the window width.
{
    const probe = await session({ ...viewports['1366'] });
    await probe.page.goto(`${base}/tree`);
    await probe.page.locator('.zoom-canvas-wrapper').waitFor();
    const canvasWidth = await probe.page.evaluate(() => document.querySelector('.zoom-canvas-wrapper').clientWidth);
    await probe.context.close();
    const width = 1366 - canvasWidth + 670;
    console.log('narrow window width', width, '(canvas at 1366:', canvasWidth, ')');

    const { context, page } = await session({ width, height: 768 });
    await shot(page, '/tree', 'narrow-proband');
    await shot(page, `/tree/${couples['8-children']}`, 'narrow-couple-8-children');
    await context.close();
}

// Dark mode.
{
    const { context, page } = await session({ ...viewports['1366'], scheme: 'dark' });
    await shot(page, `/tree/${couples['5-children']}`, 'dark-couple-5-children-1366');
    await context.close();
}

await browser.close();
