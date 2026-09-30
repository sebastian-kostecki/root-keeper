// Screenshot set for context/changes/auth-entry (logged-out entry: `/`, login, register, confirm password).
// Structure follows context/changes/app-shell-sidebar/screenshots/shots.mjs.
// Run:   LD_LIBRARY_PATH=/tmp/chromelibs/x/usr/lib/x86_64-linux-gnu node shots.mjs <out-dir>
//   Chromium needs the LD_LIBRARY_PATH above on this host (bare `node` fails on libnspr4.so).
// States (light and dark, 1280 and 390):
//   guest: `/`, /login, /login after a failed attempt (error), /login with keyboard focus on the submit button,
//          /register, /register submitted empty (errors).
//   signed in: `/`, /user/confirm-password.
// Playwright from the npx cache; not a repo dependency. Needs Sail up at localhost:8000 and the demo user
// (TreeViewDemoSeeder). One real login per run; failed ones use unknown addresses (throttle is per e-mail + IP).
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

async function signedInState() {
    const context = await browser.newContext();
    const page = await context.newPage();
    await page.goto(`${base}/login`);
    await page.fill('input[name=email]', demo);
    await page.fill('input[name=password]', 'password');
    await Promise.all([page.waitForURL((u) => !u.pathname.startsWith('/login')), page.click('button[type=submit]')]);
    const state = await context.storageState();
    await context.close();
    return state;
}

async function snap(page, name) {
    await page.waitForTimeout(350);
    await page.screenshot({ path: `${out}/${name}.png`, fullPage: true });
    console.log(name);
}

async function visit(page, url) {
    await page.goto(`${base}${url}`);
    await page.waitForLoadState('networkidle');
}

const signedIn = await signedInState();

for (const scheme of ['light', 'dark']) {
    for (const [width, height] of [
        [1280, 800],
        [390, 844],
    ]) {
        const tag = `${scheme}-${width}`;
        const viewport = { width, height };

        const guest = await browser.newContext({ viewport, colorScheme: scheme });
        const page = await guest.newPage();
        await visit(page, '/');
        await snap(page, `${tag}-guest-home`);
        await visit(page, '/login');
        await snap(page, `${tag}-login`);
        await page.locator('button[type=submit]').focus();
        await page.keyboard.press('Shift+Tab');
        await page.keyboard.press('Tab');
        await snap(page, `${tag}-login-focus-submit`);
        await visit(page, '/register');
        await snap(page, `${tag}-register`);
        await page.evaluate(() => document.querySelectorAll('[required]').forEach((el) => el.removeAttribute('required')));
        await page.click('button[type=submit]');
        await page.waitForSelector('[data-slot="input-error"]');
        await snap(page, `${tag}-register-errors`);
        await guest.close();

        // Fortify throttles per e-mail and IP, so each failed attempt uses its own unknown address.
        const failedContext = await browser.newContext({ viewport, colorScheme: scheme });
        const failedPage = await failedContext.newPage();
        await visit(failedPage, '/login');
        await failedPage.fill('input[name=email]', `nobody-${tag}@example.com`);
        await failedPage.fill('input[name=password]', 'wrong-password');
        await failedPage.click('button[type=submit]');
        await failedPage.waitForSelector('[data-slot="input-error"]');
        await snap(failedPage, `${tag}-login-error`);
        await failedContext.close();

        const user = await browser.newContext({ viewport, colorScheme: scheme, storageState: signedIn });
        const userPage = await user.newPage();
        await visit(userPage, '/');
        await snap(userPage, `${tag}-signed-in-home`);
        await visit(userPage, '/user/confirm-password');
        await snap(userPage, `${tag}-confirm-password`);
        await user.close();
    }
}

await browser.close();
