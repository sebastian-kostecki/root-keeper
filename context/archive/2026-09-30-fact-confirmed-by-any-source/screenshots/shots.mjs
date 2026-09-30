// Gate screenshot set for context/changes/fact-confirmed-by-any-source (kitchen sink: TreeViewDemoSeeder).
// Structure copied from context/archive/2026-09-30-source-description-in-modal/screenshots/shots.mjs.
// Run: LD_LIBRARY_PATH=<nss/nspr/asound libs> node shots.mjs <out-dir> [before|after]
//   Chromium needs LD_LIBRARY_PATH=/tmp/chromelibs/x/usr/lib/x86_64-linux-gnu on this host
//   (bare `node` fails on libnspr4.so).
//   before: shot at the end of Phase 1, before any frontend edit (the badge still says "Potwierdzone aktem").
//   after:  shot after Phase 2. Both flavours cover the same states, so before/ and after/ compare one to one.
// States: the persons list (Genowefa's row); "Przegląd" for Jan (birth, marriage with three kinds), Genowefa
//   (birth akt, death gravestone), Wiktor (akt and gravestone), Helena (other only), Leon (no source);
//   "Źródła" for Jan and Genowefa; "Rodzina" for Jan (marriage row); the picker from Leon's death and from
//   Jan's birth (first disabled card scrolled into view); Genowefa's death section in edit mode (the hint).
// Playwright from the npx cache; not a repo dependency. Out dir is created if missing.
// Ids are read from the rendered lists by text, not hard-coded.
// Output: <theme>-<width>-<state>.png
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
    await go(page, '/persons');
    await snap(page, prefix, 'list', true);

    await openPerson(page, ids.jan, 'Przegląd');
    await snap(page, prefix, 'overview-jan');
    await openPerson(page, ids.genowefa, 'Przegląd');
    await snap(page, prefix, 'overview-genowefa');
    await openPerson(page, ids.wiktor, 'Przegląd');
    await snap(page, prefix, 'overview-wiktor');
    await openPerson(page, ids.helena, 'Przegląd');
    await snap(page, prefix, 'overview-helena');
    await openPerson(page, ids.leon, 'Przegląd');
    await snap(page, prefix, 'overview-leon');

    await openPerson(page, ids.jan, 'Źródła');
    await snap(page, prefix, 'sources-jan');
    await openPerson(page, ids.genowefa, 'Źródła');
    await snap(page, prefix, 'sources-genowefa');

    await openPerson(page, ids.jan, 'Rodzina');
    await snap(page, prefix, 'family-jan');

    // Picker from Leon's death (no source yet): "Powiąż ..." in the "Zgon" section.
    // after flavour: the copy is "Powiąż źródło" / "Powiąż kolejne źródło"; before keeps the old "Powiąż akt" match.
    const linkButton = flavour === 'after' ? /Powiąż (kolejne )?źródło/ : /Powiąż/;
    await openPerson(page, ids.leon, 'Przegląd');
    await page.getByRole('region', { name: 'Zgon', exact: true }).getByRole('button', { name: linkButton }).click();
    await snap(page, prefix, 'picker-leon-death');
    await closeDialogs(page);

    // Picker from Jan's birth: first "Powiąż ..." on the "Źródła" tab is the birth group; scroll to a disabled card.
    await openPerson(page, ids.jan, 'Źródła');
    await page.getByRole('button', { name: linkButton }).first().click();
    await page.waitForTimeout(500);
    await page.locator('[role=dialog] button:disabled').first().scrollIntoViewIfNeeded();
    await snap(page, prefix, 'picker-jan-birth');
    await closeDialogs(page);

    // Genowefa's death section in edit mode (the hint).
    await openPerson(page, ids.genowefa, 'Przegląd');
    await page.getByRole('button', { name: 'Edytuj Zgon', exact: true }).click();
    await page.waitForTimeout(400);
    await snap(page, prefix, 'edit-death-genowefa');
}

const probe = await session({ width: 1280, height: 900, scheme: 'light' });
const ids = {
    jan: await personId(probe.page, 'Jan Kowalski'),
    genowefa: await personId(probe.page, 'Pełnodanowska'),
    wiktor: await personId(probe.page, 'Skanowski'),
    helena: await personId(probe.page, 'Helena Kowalska'),
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
