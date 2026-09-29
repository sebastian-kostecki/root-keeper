// Before/after screenshot set for context/changes/tree-family-sheet-ui (kitchen sink: TreeViewDemoSeeder).
// Run: LD_LIBRARY_PATH=<nss/nspr/asound libs> node shots.mjs <out-dir>  (Playwright from the npx cache; not a repo dependency).
// The LD_LIBRARY_PATH need remains. Out dir (before/ or after/) is created if missing.
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

async function session({ email, width, height = 900, scheme = 'light' }) {
    const context = await browser.newContext({
        viewport: { width, height },
        colorScheme: scheme,
        deviceScaleFactor: 1,
    });
    const page = await context.newPage();
    if (email) {
        await page.goto(`${base}/login`);
        await page.fill('input[name=email]', email);
        await page.fill('input[name=password]', 'password');
        await Promise.all([page.waitForURL((u) => !u.pathname.startsWith('/login')), page.click('button[type=submit]')]);
    }
    return { context, page };
}

async function shot(page, path, name, { full = true } = {}) {
    await page.goto(`${base}${path}`);
    await page.waitForLoadState('networkidle');
    await page.screenshot({ path: `${out}/${name}.png`, fullPage: full });
    console.log(name, page.url());
}

// Keyboard focus: tab into the sheet until a person card link has focus, then clip around it.
async function focusCard(page, name) {
    await page.goto(`${base}/tree`);
    await page.waitForLoadState('networkidle');
    for (let i = 0; i < 60; i++) {
        await page.keyboard.press('Tab');
        const href = await page.evaluate(() => document.activeElement?.getAttribute('href') ?? '');
        if (/\/tree\/\d+/.test(href)) break;
    }
    const box = await page.evaluate(() => {
        const r = document.activeElement.getBoundingClientRect();
        return { x: r.x - 40, y: r.y - 40, width: r.width + 80, height: r.height + 80 };
    });
    await page.screenshot({ path: `${out}/${name}.png`, clip: box });
    console.log(name);
}

// Hover on a card.
async function hoverCard(page, name) {
    await page.goto(`${base}/tree`);
    await page.waitForLoadState('networkidle');
    await page.mouse.move(0, 0);
    const card = page.locator('a[href*="/tree/1"]').first();
    await card.hover();
    const hb = await card.boundingBox();
    await page.screenshot({ path: `${out}/${name}.png`, clip: { x: hb.x - 20, y: hb.y - 20, width: hb.width + 40, height: hb.height + 40 } });
    console.log(name);
}

const demo = 'tree-demo@example.com';

// Proband id (Jan) from /tree: Inertia page props (sheet.focus.id), fallback to the breadcrumb link.
let jan;
{
    const { context, page } = await session({ email: demo, width: 1440 });
    await page.goto(`${base}/tree`);
    await page.waitForLoadState('networkidle');
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
// Offsets from Jan = creation order in TreeViewDemoSeeder.
const id = {
    jan,
    ewa: jan + 14, // second-union partner
    marta: jan + 16, // co-parent
    zuzanna: jan + 18, // long name
    kuba: jan + 23, // unknown-gender parent's child
    robert: jan + 24, // Nowicki couple, 5 children
};
console.log('ids', id);

// Desktop light: proband, second union, co-parent, unknown-gender parent, long name.
{
    const { context, page } = await session({ email: demo, width: 1440 });
    await shot(page, '/tree', 'desktop-light-proband');
    await shot(page, `/tree/${id.jan}?spouse=${id.ewa}`, 'desktop-light-second-union');
    await shot(page, `/tree/${id.jan}?spouse=${id.marta}`, 'desktop-light-coparent');
    await shot(page, `/tree/${id.kuba}`, 'desktop-light-unknown-gender');
    await shot(page, `/tree/${id.zuzanna}`, 'desktop-light-long-name');
    await focusCard(page, 'desktop-light-focus-card');
    await hoverCard(page, 'desktop-light-hover-card');
    // 404 for another tree's person (id 1 belongs to someone else unless demo owns it).
    await shot(page, '/tree/1', 'desktop-light-foreign-person');
    await shot(page, `/tree/${id.robert}`, 'desktop-light-five-children');
    // Sibling views touched by the layout gutter.
    await shot(page, '/persons', 'desktop-light-persons');
    await shot(page, '/scans', 'desktop-light-scans');
    await shot(page, '/settings/profile', 'desktop-light-settings-profile');
    await context.close();
}

// Desktop dark.
{
    const { context, page } = await session({ email: demo, width: 1440, scheme: 'dark' });
    await shot(page, '/tree', 'desktop-dark-proband');
    await hoverCard(page, 'desktop-dark-hover-card');
    await focusCard(page, 'desktop-dark-focus-card');
    await context.close();
}

// Mobile 390.
{
    const { context, page } = await session({ email: demo, width: 390, height: 844 });
    await shot(page, '/tree', 'mobile-light-proband-viewport', { full: false });
    await shot(page, '/tree', 'mobile-light-proband');
    // Union switch viewport, focus on proband.
    await shot(page, `/tree/${id.jan}`, 'mobile-light-union-switch', { full: false });
    await shot(page, '/persons', 'mobile-light-persons');
    await shot(page, '/scans', 'mobile-light-scans');
    await shot(page, '/settings/profile', 'mobile-light-settings-profile');
    await context.close();
}

// Empty tree (no proband).
{
    const { context, page } = await session({ email: 'tree-demo-empty@example.com', width: 1440 });
    await shot(page, '/tree', 'desktop-light-empty');
    await context.close();
}
{
    const { context, page } = await session({ email: 'tree-demo-empty@example.com', width: 390, height: 844 });
    await shot(page, '/tree', 'mobile-light-empty', { full: false });
    await context.close();
}

// Logged out.
{
    const { context, page } = await session({ width: 1440 });
    await shot(page, '/tree', 'logged-out', { full: false });
    await context.close();
}

await browser.close();
