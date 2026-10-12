// Gate screenshots for context/changes/sources-page (Phase 1 "before" set and later "after" set).
// Structure follows context/archive/2026-10-11-persons-index-ui/screenshots/shots.mjs.
// Run: node shots.mjs <out-dir>
// Needs the E2E server on 127.0.0.1:8100 (scripts/e2e-server.sh, DB `e2e`) seeded with
// TreeViewDemoSeeder (`tree-demo@example.com`). Playwright comes from the repo (@playwright/test).
// Besides the PNGs, the script logs the measurements the verdict needs (:focus-visible, bar
// height, sticky cell colour, toggle overflow, broken thumbnail).
import fs from 'node:fs';
import { chromium } from '@playwright/test';

const base = 'http://127.0.0.1:8100';
const out = process.argv[2];
if (!out) {
    throw new Error('Usage: node shots.mjs <out-dir>');
}
fs.mkdirSync(out, { recursive: true });

const DESKTOP = { width: 1440, height: 900 };
const MOBILE = { width: 375, height: 812 };
const demo = 'tree-demo@example.com';
const tmpDir = '/home/sebastian/.claude/jobs/9c419811/tmp';
fs.mkdirSync(tmpDir, { recursive: true });
const authState = `${tmpDir}/sources-page-auth-state.json`;

const browser = await chromium.launch();

// Log in once (Fortify throttles repeated logins), then reuse the session state for every context.
if (!fs.existsSync(authState)) {
    const loginContext = await browser.newContext();
    const loginPage = await loginContext.newPage();
    await loginPage.goto(`${base}/login`);
    await loginPage.fill('input[name=email]', demo);
    await loginPage.fill('input[name=password]', 'password');
    await Promise.all([
        loginPage.waitForURL((url) => !url.pathname.startsWith('/login')),
        loginPage.click('button[type=submit]'),
    ]);
    await loginContext.storageState({ path: authState });
    await loginContext.close();
}

async function session(viewport) {
    const context = await browser.newContext({
        viewport,
        deviceScaleFactor: 1,
        colorScheme: 'light',
        storageState: authState,
    });
    const page = await context.newPage();
    return { context, page };
}

async function visit(page, query = '') {
    await page.goto(`${base}/sources${query}`);
    await page.waitForLoadState('networkidle');
}

async function snap(page, name, fullPage = false) {
    await page.screenshot({ path: `${out}/${name}.png`, fullPage });
    console.log(name);
}

async function run(viewport, fn) {
    const { context, page } = await session(viewport);
    try {
        await fn(page);
    } finally {
        await context.close();
    }
}

// 01-03: gallery desktop and mobile, table desktop.
await run(DESKTOP, async (page) => {
    await visit(page);
    await snap(page, '01-gallery-desktop', true);
});
await run(MOBILE, async (page) => {
    await visit(page);
    const bar = await page.getByRole('search').boundingBox();
    console.log('C4 search bar on 375 px: top', bar?.y, 'height', bar?.height);
    await snap(page, '02-gallery-mobile', true);
});
await run(DESKTOP, async (page) => {
    await visit(page, '?view=tabela');
    await snap(page, '03-table-desktop', true);
});

// 04: open source in the table on mobile, table scrolled right (sticky first cell).
await run(MOBILE, async (page) => {
    await visit(page, '?view=tabela');
    await page.locator('tbody a[href*="source="]').first().click();
    await page.waitForURL(/source=\d+/);
    await page.locator('tbody tr[data-state="selected"]').waitFor({ state: 'visible' });
    await page
        .locator('.overflow-x-auto')
        .first()
        .evaluate((el) => {
            el.scrollLeft = el.scrollWidth;
        });
    const colours = await page.locator('tbody tr[data-state="selected"]').evaluate((row) => {
        const cells = row.querySelectorAll('td');
        return {
            sticky: getComputedStyle(cells[0]).backgroundColor,
            rest: getComputedStyle(cells[2]).backgroundColor,
        };
    });
    console.log('C2 selected row, sticky cell bg', colours.sticky, 'other cell bg', colours.rest);
    await snap(page, '04-table-mobile-selected');
});

// 05: keyboard focus (Tab) onto the open gallery card.
await run(DESKTOP, async (page) => {
    await visit(page);
    const openHref = await page.locator('ul a[href*="source="]').first().getAttribute('href');
    console.log('C1 href of first card', openHref);
    const openId = new URL(openHref, base).searchParams.get('source');
    await visit(page, `?source=${openId}`);
    let reached = false;
    for (let i = 0; i < 120 && !reached; i += 1) {
        await page.keyboard.press('Tab');
        reached = await page.evaluate(
            () => document.activeElement?.getAttribute('aria-current') === 'true',
        );
    }
    const focusVisible = await page.evaluate(() =>
        document.activeElement?.matches(':focus-visible') ?? false,
    );
    const shadow = await page.evaluate(() =>
        getComputedStyle(document.activeElement).boxShadow,
    );
    console.log('C1 open card reached by Tab:', reached, ':focus-visible =', focusVisible, 'box-shadow =', shadow);
    await snap(page, '05-focus-selected-card');
});

// 06: active filter and "Wyczyść filtry" on mobile.
await run(MOBILE, async (page) => {
    await visit(page, '?year_from=1890&year_to=1895');
    await snap(page, '06-filters-active-mobile', true);
});

// 07: reversed year range on desktop (1900–1850).
await run(DESKTOP, async (page) => {
    await visit(page, '?year_from=1900&year_to=1850');
    await snap(page, '07-year-reversed-desktop');
});

// 08: a kind that is empty under the place filter (the kind switch is disabled, so the kind comes from the address).
await run(DESKTOP, async (page) => {
    await visit(page);
    await page.getByLabel('Parafia / miejsce').click();
    await page.getByRole('option', { name: 'Tarnów', exact: true }).click();
    await page.waitForURL(/[?&]place=/);
    await page.waitForLoadState('networkidle');
    const place = new URL(page.url()).searchParams.get('place');
    const kinds = [
        { value: 'akty', label: /^Akty/ },
        { value: 'nagrobki', label: /^Nagrobki/ },
        { value: 'inne', label: /^Inne/ },
    ];
    let emptyKind = null;
    for (const kind of kinds) {
        const radio = page.getByRole('radio', { name: kind.label });
        if (await radio.isDisabled()) {
            emptyKind = kind.value;
            break;
        }
    }
    console.log('C5 place', place, 'disabled kind', emptyKind);
    await visit(page, `?place=${encodeURIComponent(place)}&kind=${emptyKind ?? 'inne'}`);
    await snap(page, '08-empty-kind-with-place');
});

// 09: broken thumbnail (the thumbnail request is aborted).
await run(DESKTOP, async (page) => {
    await page.route(
        (url) => url.pathname.includes('/thumbnail'),
        (route) => route.abort(),
    );
    await visit(page);
    await snap(page, '09-thumbnail-broken');
});

// 10: kind switch on mobile (D9: counts with three digits).
await run(MOBILE, async (page) => {
    await visit(page);
    const group = page.getByRole('radiogroup', { name: 'Rodzaj źródła' }).or(
        page.locator('[role="radiogroup"]').first(),
    );
    const overflow = await group.evaluate((el) => ({
        scroll: el.scrollWidth,
        client: el.clientWidth,
        items: [...el.querySelectorAll('[role="radio"]')].map((item) => ({
            text: item.textContent,
            scroll: item.scrollWidth,
            client: item.clientWidth,
        })),
    }));
    console.log('D9 kind toggle on 375 px', JSON.stringify(overflow));
    await snap(page, '10-kind-toggle-mobile');
});

// 11: filters expanded on mobile ("Filtry (N)" opens the block with the four fields).
await run(MOBILE, async (page) => {
    await visit(page, '?year_from=1850&year_to=1900');
    await page.getByRole('button', { name: 'Filtry (2)', exact: true }).click();
    await page.getByLabel('Rok od').waitFor({ state: 'visible' });
    await snap(page, '11-filters-expanded-mobile', true);
});

// 12: a kind visit cut by the network on desktop (the error and "Spróbuj ponownie" beside the bar).
await run(DESKTOP, async (page) => {
    await page.route(
        (url) => url.pathname === '/sources' && url.searchParams.has('kind'),
        (route) => (route.request().headers()['x-inertia'] ? route.abort() : route.continue()),
    );
    await visit(page);
    await page.getByRole('radio', { name: /^Akty/ }).click();
    await page.getByRole('alert').waitFor({ state: 'visible' });
    await snap(page, '12-visit-error-desktop');
});

await browser.close();
