// Gate screenshot set for context/changes/person-modal (kitchen sink: TreeViewDemoSeeder).
// Structure copied from context/archive/2026-09-29-tree-sheet-variant-c/screenshots/shots.mjs.
// Run: LD_LIBRARY_PATH=<nss/nspr/asound libs if needed> node shots.mjs <out-dir>
// (Playwright from the npx cache; not a repo dependency.) Out dir is created if missing.
// Person ids are read from the /persons list by name, not hard-coded.
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

async function session({ width, height = 900, scheme = 'light' }) {
    const context = await browser.newContext({ viewport: { width, height }, colorScheme: scheme, deviceScaleFactor: 1 });
    const page = await context.newPage();
    await page.goto(`${base}/login`);
    await page.fill('input[name=email]', demo);
    await page.fill('input[name=password]', 'password');
    await Promise.all([page.waitForURL((u) => !u.pathname.startsWith('/login')), page.click('button[type=submit]')]);
    return { context, page };
}

async function snap(page, name) {
    await page.waitForTimeout(350);
    await page.screenshot({ path: `${out}/${name}.png`, fullPage: false });
    console.log(name);
}

async function idOf(page, name) {
    await page.goto(`${base}/persons`);
    await page.waitForLoadState('networkidle');
    const id = await page.evaluate((needle) => {
        const link = [...document.querySelectorAll('a[href*="person="]')].find((a) => a.textContent.includes(needle));
        return link ? Number(new URL(link.href).searchParams.get('person')) : null;
    }, name);
    if (!id) {
        throw new Error(`No person "${name}" on /persons`);
    }
    return id;
}

async function open(page, personId) {
    await page.goto(`${base}/persons?person=${personId}`);
    await page.waitForLoadState('networkidle');
    await page.waitForSelector('[role=dialog]');
}

const pencil = (page, title) => page.getByRole('button', { name: `Edytuj ${title}`, exact: true });

async function shots(scheme, ids) {
    const p = scheme === 'dark' ? 'dark' : 'light';
    const { context, page } = await session({ width: 1280, scheme });
    const dialog = () => page.locator('[role=dialog]').first();

    await open(page, ids.jan);
    await snap(page, `${p}-overview-full`);
    await open(page, ids.leon);
    await snap(page, `${p}-overview-approx-death`);
    await open(page, ids.nina);
    await snap(page, `${p}-overview-sparse`);

    await open(page, ids.jan);
    await pencil(page, 'Dane osobowe').click();
    await snap(page, `${p}-edit-personal`);

    await open(page, ids.jan);
    await pencil(page, 'Urodzenie').click();
    await page.fill('#birth-date', '12.3.1948');
    await snap(page, `${p}-edit-birth-hint`);
    await page.fill('#birth-date', '12.3.48');
    await snap(page, `${p}-edit-birth-ambiguous`);

    await open(page, ids.leon);
    await pencil(page, 'Zgon').click();
    await page.fill('#death-date', 'zima 2015');
    await page.getByRole('button', { name: 'Zapisz', exact: true }).click();
    await snap(page, `${p}-edit-death-error`);

    // Death with a scan: unchecking "Osoba nie żyje" is rejected by the server.
    await open(page, ids.wiktor);
    await pencil(page, 'Zgon').click();
    await page.locator('#is_deceased').click();
    await page.getByRole('button', { name: 'Zapisz', exact: true }).click();
    await page.waitForLoadState('networkidle');
    await snap(page, `${p}-edit-death-scan-error`);

    // Guard bar, keyboard exit (Esc keeps focus in the field, so no blur/change event fires).
    await open(page, ids.jan);
    await pencil(page, 'Dane osobowe').click();
    await page.locator('#first_name').pressSequentially('usz');
    await page.keyboard.press('Escape');
    await page.waitForSelector('[role=alert]');
    await snap(page, `${p}-guard-bar`);

    // Guard bar, real pointer click on a tab: the blur fires `change`, which re-marks the card dirty and
    // clears the pending exit (defect, see verdict). The shot records what a user actually sees.
    await open(page, ids.jan);
    await pencil(page, 'Dane osobowe').click();
    await page.locator('#first_name').pressSequentially('usz');
    await page.getByRole('tab', { name: /Rodzina/ }).click();
    await page.waitForTimeout(600);
    console.log(`${p} tab-click guard bar shown:`, (await page.locator('[role=alert]').count()) > 0);
    await snap(page, `${p}-guard-tab-click`);

    // "⋯" menu: non-proband and proband.
    await open(page, ids.leon);
    await page.getByRole('button', { name: 'Więcej działań' }).click();
    await snap(page, `${p}-menu-non-proband`);
    await open(page, ids.jan);
    await page.getByRole('button', { name: 'Więcej działań' }).click();
    await snap(page, `${p}-menu-proband`);

    await open(page, ids.jan);
    await page.getByRole('tab', { name: /Rodzina/ }).click();
    await snap(page, `${p}-family`);
    await page.getByRole('button', { name: /Więcej działań|Działania/ }).last().click().catch(() => {});
    await snap(page, `${p}-family-row-menu`);
    await open(page, ids.jan);
    await page.getByRole('tab', { name: /Źródła/ }).click();
    await snap(page, `${p}-sources`);

    await page.goto(`${base}/persons`);
    await page.waitForLoadState('networkidle');
    await page.getByRole('button', { name: 'Dodaj osobę' }).first().click();
    await page.waitForSelector('[role=dialog]');
    await snap(page, `${p}-create`);
    await page.fill('#birth-date', 'marzec 1948');
    await snap(page, `${p}-create-filled`);

    // Keyboard focus: a tab trigger and a pencil.
    await open(page, ids.jan);
    await page.getByRole('tab', { name: 'Przegląd' }).focus();
    await page.keyboard.press('Shift+Tab');
    await page.keyboard.press('Tab');
    await snap(page, `${p}-focus-tab`);
    await pencil(page, 'Urodzenie').focus();
    await page.keyboard.press('Shift+Tab');
    await page.keyboard.press('Tab');
    await snap(page, `${p}-focus-pencil`);
    void dialog;
    await context.close();
}

const probe = await session({ width: 1280 });
const ids = {
    jan: await idOf(probe.page, 'Jan Kowalski'),
    leon: await idOf(probe.page, 'Leon Kowalski'),
    nina: await idOf(probe.page, 'Nina Rzadka'),
    wiktor: await idOf(probe.page, 'Wiktor Skanowski'),
};
await probe.context.close();
console.log('ids', ids);

await shots('light', ids);
await shots('dark', ids);

// Mobile 390: overview, full-screen section edit, create.
{
    const { context, page } = await session({ width: 390, height: 844 });
    await open(page, ids.jan);
    await snap(page, 'mobile-overview');
    await pencil(page, 'Urodzenie').click();
    await page.fill('#birth-date', '12.3.1948');
    await snap(page, 'mobile-section-edit');
    await page.goto(`${base}/persons`);
    await page.waitForLoadState('networkidle');
    await page.getByRole('button', { name: 'Dodaj osobę' }).first().click();
    await page.waitForSelector('[role=dialog]');
    await snap(page, 'mobile-create');
    await context.close();
}

await browser.close();
