// Gate screenshot set for context/changes/persons-list-views (kitchen sink: TreeViewDemoSeeder).
// Structure follows context/archive/2026-09-29-person-modal/screenshots/shots.mjs.
// Run: node shots.mjs <out-dir> [before|after]   (default mode: after)
//   before: only the shots that make sense on the old single-list page (/persons, no-results, searching, empty tree).
//   after:  everything, including Lista/Tabela, `-urodzenie` sort and the view switcher.
// Playwright comes from the npx cache (not a repo dependency). Needs Sail up at localhost:8000 and the demo user.
// Add LD_LIBRARY_PATH=<nss/nspr/asound libs> if chromium fails to start.
import fs from 'node:fs';
import { chromium } from '/home/sebastian/.npm/_npx/e41f203b7505f1fb/node_modules/playwright/index.mjs';

const base = 'http://localhost:8000';
const out = process.argv[2];
const mode = process.argv[3] ?? 'after';
if (!out || !['before', 'after'].includes(mode)) {
    throw new Error('Usage: node shots.mjs <out-dir> [before|after]');
}
const after = mode === 'after';
fs.mkdirSync(out, { recursive: true });
const browser = await chromium.launch();
const demo = 'tree-demo@example.com';

async function session({ width, height = 900, scheme = 'light', login = true }) {
    const context = await browser.newContext({ viewport: { width, height }, colorScheme: scheme, deviceScaleFactor: 1 });
    const page = await context.newPage();
    if (login) {
        await page.goto(`${base}/login`);
        await page.fill('input[name=email]', demo);
        await page.fill('input[name=password]', 'password');
        await Promise.all([page.waitForURL((u) => !u.pathname.startsWith('/login')), page.click('button[type=submit]')]);
    }
    return { context, page };
}

async function snap(page, name, fullPage = false) {
    await page.waitForTimeout(350);
    await page.screenshot({ path: `${out}/${name}.png`, fullPage });
    console.log(name);
}

async function visit(page, query = '') {
    await page.goto(`${base}/persons${query}`);
    await page.waitForLoadState('networkidle');
}

// Person ids must still resolve from /persons (earlier gates rely on this selector).
async function checkLookup(page) {
    await visit(page);
    const count = await page.locator('a[href*="person="]').count();
    console.log('person links on /persons:', count);
    if (count === 0) {
        throw new Error('No a[href*="person="] links on /persons');
    }
}

async function set(scheme, width, height) {
    const tag = `${scheme}-${width === 390 ? 'mobile' : 'desktop'}`;
    const { context, page } = await session({ width, height, scheme });

    if (after) {
        await visit(page);
        await snap(page, `${tag}-lista`, true);
        await visit(page, '?view=tabela');
        await snap(page, `${tag}-tabela`, true);
        await visit(page, '?view=tabela&sort=-urodzenie');
        await snap(page, `${tag}-tabela-sort-urodzenie-desc`, true);
        // Scroll the table sideways so the ⋯ button clears the sticky first column on narrow screens.
        await page.evaluate(() => {
            const box = document.querySelector('[data-slot=table-container]');
            if (box) box.scrollLeft = box.scrollWidth;
        });
        await page.getByRole('button', { name: /^Akcje:/ }).first().click();
        await snap(page, `${tag}-tabela-row-menu`);
    } else {
        await visit(page);
        await snap(page, `${tag}-persons`, true);
    }

    // No results.
    await visit(page, '?q=zzzzzz');
    await snap(page, `${tag}-no-results`);

    // Searching: hold the search response so the in-flight state can be captured.
    await visit(page, after ? '?view=tabela' : '');
    await page.route(/\/persons\?.*q=/, async (route) => {
        await new Promise((resolve) => setTimeout(resolve, 2500));
        await route.continue();
    });
    await page.locator('input[type=search]').fill('Kow');
    await page.waitForTimeout(700);
    await snap(page, `${tag}-searching`);
    await page.unroute(/\/persons\?.*q=/);
    await context.close();
}

async function emptyTree(scheme, width, height) {
    const tag = `${scheme}-${width === 390 ? 'mobile' : 'desktop'}`;
    const { context, page } = await session({ width, height, scheme, login: false });
    const email = `shots-${Date.now()}-${Math.random().toString(36).slice(2, 8)}@example.com`;
    await page.goto(`${base}/register`);
    await page.fill('input[name=name]', 'Shots Empty');
    await page.fill('input[name=email]', email);
    await page.fill('input[name=password]', 'password-1234-x');
    await page.fill('input[name=password_confirmation]', 'password-1234-x');
    await Promise.all([page.waitForURL((u) => !u.pathname.startsWith('/register')), page.click('button[type=submit]')]);
    await visit(page);
    await snap(page, `${tag}-empty-tree`);
    await context.close();
}

const probe = await session({ width: 1280 });
await checkLookup(probe.page);
await probe.context.close();

for (const scheme of ['light', 'dark']) {
    await set(scheme, 1280, 900);
    await set(scheme, 390, 844);
}
await emptyTree('light', 1280, 900);
await emptyTree('dark', 1280, 900);
await emptyTree('light', 390, 844);

await browser.close();
