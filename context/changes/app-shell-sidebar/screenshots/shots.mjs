// Screenshot set for context/changes/app-shell-sidebar (the authenticated app shell).
// Structure follows context/archive/2026-09-30-sources-list-views/screenshots/shots.mjs.
// Run:   LD_LIBRARY_PATH=/tmp/chromelibs/x/usr/lib/x86_64-linux-gnu node shots.mjs <out-dir>
//   Chromium needs the LD_LIBRARY_PATH above on this host (bare `node` fails on libnspr4.so).
// States (light and dark):
//   1280: sidebar expanded on /tree, /persons, /sources, /settings/profile; sidebar collapsed (icon rail);
//         collapsed + tooltip on a nav item; user menu open (expanded and collapsed); keyboard focus on a nav item.
//   390:  page with the sidebar closed; mobile sheet open; user menu open inside the sheet.
// Playwright from the npx cache; not a repo dependency. Needs Sail up at localhost:8000 and the demo users
// (TreeViewDemoSeeder).
// Output: <theme>-<width>-<state>.png
import fs from 'node:fs';

const base = 'http://localhost:8000';
const out = process.argv[2];
if (!out) {
    throw new Error('Usage: node shots.mjs <out-dir>');
}
fs.mkdirSync(out, { recursive: true });
const { chromium } = await import('/home/sebastian/.npm/_npx/e41f203b7505f1fb/node_modules/playwright/index.mjs');
const browser = await chromium.launch();
const demo = 'tree-demo@example.com';

// One login for the whole run (Fortify throttles logins at 5 per minute); every context reuses its cookies.
let storageState;
async function login() {
    const context = await browser.newContext();
    const page = await context.newPage();
    await page.goto(`${base}/login`);
    await page.fill('input[name=email]', demo);
    await page.fill('input[name=password]', 'password');
    await Promise.all([page.waitForURL((u) => !u.pathname.startsWith('/login')), page.click('button[type=submit]')]);
    storageState = await context.storageState();
    await context.close();
}

async function session({ width, height, scheme, sidebarOpen = true }) {
    const context = await browser.newContext({
        viewport: { width, height },
        colorScheme: scheme,
        deviceScaleFactor: 1,
        storageState,
    });
    await context.addCookies([{ name: 'sidebar_state', value: String(sidebarOpen), url: base }]);
    return { context, page: await context.newPage() };
}

async function snap(page, name) {
    await page.waitForTimeout(350);
    await page.screenshot({ path: `${out}/${name}.png`, fullPage: false });
    console.log(name);
}

async function visit(page, url) {
    await page.goto(`${base}${url}`);
    await page.waitForLoadState('networkidle');
}

await login();

for (const scheme of ['light', 'dark']) {
    {
        const tag = `${scheme}-1280`;
        const { context, page } = await session({ width: 1280, height: 800, scheme });
        for (const [name, url] of Object.entries({
            tree: '/tree',
            persons: '/persons',
            sources: '/sources',
            settings: '/settings/profile',
        })) {
            await visit(page, url);
            await snap(page, `${tag}-expanded-${name}`);
        }

        await visit(page, '/persons');
        await page.locator('[data-test="sidebar-menu-button"]').click();
        await snap(page, `${tag}-expanded-user-menu`);
        await page.keyboard.press('Escape');

        await visit(page, '/persons');
        for (let i = 0; i < 12; i++) {
            await page.keyboard.press('Tab');
            if (await page.evaluate(() => document.activeElement?.getAttribute('href') === '/sources')) break;
        }
        await snap(page, `${tag}-expanded-focus-nav`);
        await context.close();

        const collapsed = await session({ width: 1280, height: 800, scheme, sidebarOpen: false });
        await visit(collapsed.page, '/persons');
        await snap(collapsed.page, `${tag}-collapsed-persons`);
        await collapsed.page.locator('[data-sidebar="menu-button"][href="/sources"]').hover();
        await snap(collapsed.page, `${tag}-collapsed-tooltip`);
        await collapsed.page.mouse.move(640, 400);
        await collapsed.page.locator('[data-test="sidebar-menu-button"]').click();
        await snap(collapsed.page, `${tag}-collapsed-user-menu`);
        await collapsed.context.close();
    }

    {
        const tag = `${scheme}-390`;
        const { context, page } = await session({ width: 390, height: 844, scheme });
        await visit(page, '/persons');
        await snap(page, `${tag}-closed-persons`);
        await page.locator('[data-sidebar="trigger"]').click();
        await snap(page, `${tag}-sheet-open`);
        await page.locator('[data-test="sidebar-menu-button"]').click();
        await snap(page, `${tag}-sheet-user-menu`);
        await context.close();
    }
}

await browser.close();
