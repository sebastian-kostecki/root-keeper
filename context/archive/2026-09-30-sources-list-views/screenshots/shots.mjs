// Gate screenshot set for context/changes/sources-list-views (kitchen sink: TreeViewDemoSeeder).
// Structure follows context/archive/2026-09-30-matching-sources-first/screenshots/shots.mjs.
// Run:   LD_LIBRARY_PATH=/tmp/chromelibs/x/usr/lib/x86_64-linux-gnu node shots.mjs <out-dir> [persons|sources|sources-after|all]
// Compare: node shots.mjs --compare <dir-a> <dir-b>   (byte comparison of same-named PNGs, one line per file)
//   Chromium needs the LD_LIBRARY_PATH above on this host (bare `node` fails on libnspr4.so).
// Sets:
//   persons: /persons control set (must stay byte-identical before/after Phase 2): Lista, Tabela, Tabela sorted by
//            -urodzenie, no results, empty tree; light and dark at 1280 and 390 px.
//   sources: /sources as rendered now; light and dark at 1280 and 390 px. (The after-set for /sources is extended
//            by the phases that build the new view.)
//   sources-after: the states of the new /sources view (Phase 4): Galeria, Tabela, Tabela by year, search with counters,
//            one kind, no match, empty kind, empty tree (light/dark x 1280/390), plus at 1280 the open menu, the edit
//            dialog, and keyboard focus on a card and on a row link. `all` runs persons, sources and sources-after.
// Playwright from the npx cache; not a repo dependency. Needs Sail up at localhost:8000 and the demo users.
// Output: <theme>-<width>-<state>.png
import fs from 'node:fs';
import path from 'node:path';

const base = 'http://localhost:8000';

if (process.argv[2] === '--compare') {
    const [a, b] = process.argv.slice(3);
    if (!a || !b) {
        throw new Error('Usage: node shots.mjs --compare <dir-a> <dir-b>');
    }
    let differs = 0;
    for (const file of fs.readdirSync(a).filter((f) => f.endsWith('.png')).sort()) {
        const other = path.join(b, file);
        if (!fs.existsSync(other)) {
            console.log(`missing   ${file}`);
            differs++;
        } else if (fs.readFileSync(path.join(a, file)).equals(fs.readFileSync(other))) {
            console.log(`identical ${file}`);
        } else {
            console.log(`differs   ${file}`);
            differs++;
        }
    }
    process.exit(differs === 0 ? 0 : 1);
}

const out = process.argv[2];
const set = process.argv[3] ?? 'all';
if (!out || !['persons', 'sources', 'sources-after', 'all'].includes(set)) {
    throw new Error('Usage: node shots.mjs <out-dir> [persons|sources|sources-after|all]');
}
fs.mkdirSync(out, { recursive: true });
const { chromium } = await import('/home/sebastian/.npm/_npx/e41f203b7505f1fb/node_modules/playwright/index.mjs');
const browser = await chromium.launch();
const demo = 'tree-demo@example.com';
const empty = 'tree-demo-empty@example.com';

async function session({ width, height, scheme, email = demo }) {
    const context = await browser.newContext({ viewport: { width, height }, colorScheme: scheme, deviceScaleFactor: 1 });
    const page = await context.newPage();
    await page.goto(`${base}/login`);
    await page.fill('input[name=email]', email);
    await page.fill('input[name=password]', 'password');
    await Promise.all([page.waitForURL((u) => !u.pathname.startsWith('/login')), page.click('button[type=submit]')]);
    return { context, page };
}

async function snap(page, name, fullPage = true) {
    await page.waitForTimeout(350);
    await page.screenshot({ path: `${out}/${name}.png`, fullPage });
    console.log(name);
}

async function visit(page, url) {
    await page.goto(`${base}${url}`);
    await page.waitForLoadState('networkidle');
}

const sizes = [
    [1280, 900],
    [390, 844],
];

for (const scheme of ['light', 'dark']) {
    for (const [width, height] of sizes) {
        const tag = `${scheme}-${width}`;

        if (set === 'persons' || set === 'all') {
            const { context, page } = await session({ width, height, scheme });
            await visit(page, '/persons');
            await snap(page, `${tag}-persons-lista`);
            await visit(page, '/persons?view=tabela');
            await snap(page, `${tag}-persons-tabela`);
            await visit(page, '/persons?view=tabela&sort=-urodzenie');
            await snap(page, `${tag}-persons-tabela-sort-urodzenie-desc`);
            await visit(page, '/persons?q=zzzzzz');
            await snap(page, `${tag}-persons-no-results`);
            await context.close();

            const emptySession = await session({ width, height, scheme, email: empty });
            await visit(emptySession.page, '/persons');
            await snap(emptySession.page, `${tag}-persons-empty-tree`);
            await emptySession.context.close();
        }

        if (set === 'sources' || set === 'all') {
            const { context, page } = await session({ width, height, scheme });
            await visit(page, '/sources');
            await snap(page, `${tag}-sources-list`);
            await context.close();
        }

        if (set === 'sources-after' || set === 'all') {
            const { context, page } = await session({ width, height, scheme });
            const states = {
                galeria: '/sources',
                tabela: '/sources?view=tabela',
                'tabela-rok': '/sources?view=tabela&sort=rok',
                'search-counters': '/sources?q=lipn',
                'one-kind': '/sources?kind=nagrobki',
                'no-match': '/sources?q=Kowalczyk',
                'empty-kind': '/sources?q=lipn&kind=inne',
            };
            for (const [name, url] of Object.entries(states)) {
                await visit(page, url);
                await snap(page, `${tag}-sources-${name}`);
            }

            if (width === 1280) {
                await visit(page, '/sources?view=tabela');
                await page.getByRole('button', { name: /^Akcje:/ }).first().click();
                await snap(page, `${tag}-sources-menu-open`);
                await page.getByRole('menuitem', { name: 'Edytuj' }).click();
                await page.getByRole('dialog').waitFor();
                await snap(page, `${tag}-sources-edit-dialog`, false);

                await visit(page, '/sources');
                for (let i = 0; i < 30; i++) {
                    await page.keyboard.press('Tab');
                    if (await page.evaluate(() => !!document.activeElement?.closest('ul')?.querySelector('a[href*="/sources/"]') && document.activeElement?.tagName === 'A')) break;
                }
                await snap(page, `${tag}-sources-focus-card`, false);

                await visit(page, '/sources?view=tabela');
                for (let i = 0; i < 40; i++) {
                    await page.keyboard.press('Tab');
                    if (await page.evaluate(() => document.activeElement?.tagName === 'A' && !!document.activeElement.closest('td'))) break;
                }
                await snap(page, `${tag}-sources-focus-row-link`, false);
            }
            await context.close();

            const emptySession = await session({ width, height, scheme, email: empty });
            await visit(emptySession.page, '/sources');
            await snap(emptySession.page, `${tag}-sources-empty-tree`);
            await emptySession.context.close();
        }
    }
}

await browser.close();
