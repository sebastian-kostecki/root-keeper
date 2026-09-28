// Before/after screenshot set for context/changes/tree-family-sheet-ui (kitchen sink: TreeViewDemoSeeder).
// Run: LD_LIBRARY_PATH=<nss/nspr/asound libs> node shots.mjs <out-dir>  (Playwright from the npx cache; not a repo dependency).
import { chromium } from '/home/sebastian/.npm/_npx/e41f203b7505f1fb/node_modules/playwright/index.mjs';

const base = 'http://localhost:8000';
const out = process.argv[2];
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

const demo = 'tree-demo@example.com';

// Desktop light: proband, second union, co-parent, unknown-gender parent, long name.
{
    const { context, page } = await session({ email: demo, width: 1440 });
    await shot(page, '/tree', 'desktop-light-proband');
    await shot(page, '/tree/102?spouse=116', 'desktop-light-second-union');
    await shot(page, '/tree/102?spouse=118', 'desktop-light-coparent');
    await shot(page, '/tree/125', 'desktop-light-unknown-gender');
    await shot(page, '/tree/120', 'desktop-light-long-name');
    // Focus: tab into the sheet until a person card link has focus.
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
    await page.screenshot({ path: `${out}/desktop-light-focus-card.png`, clip: box });
    // Hover on a card.
    await page.mouse.move(0, 0);
    const card = page.locator('a[href*="/tree/1"]').first();
    await card.hover();
    const hb = await card.boundingBox();
    await page.screenshot({ path: `${out}/desktop-light-hover-card.png`, clip: { x: hb.x - 20, y: hb.y - 20, width: hb.width + 40, height: hb.height + 40 } });
    // 404 for another tree's person (id 1 belongs to someone else unless demo owns it).
    await shot(page, '/tree/1', 'desktop-light-foreign-person');
    await context.close();
}

// Desktop dark.
{
    const { context, page } = await session({ email: demo, width: 1440, scheme: 'dark' });
    await shot(page, '/tree', 'desktop-dark-proband');
    await context.close();
}

// Mobile 390.
{
    const { context, page } = await session({ email: demo, width: 390, height: 844 });
    await shot(page, '/tree', 'mobile-light-proband-viewport', { full: false });
    await shot(page, '/tree', 'mobile-light-proband');
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
