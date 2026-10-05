// The `test` every flow spec imports. It hands the test a unique suffix for the
// names of its records and, as the fixture's teardown, removes every record
// carrying it. Playwright runs the teardown after a red assertion and after a
// timeout too, and reports a teardown error separately from the test's own.
// The fixture has its own timeout, so a test that ran out of time still leaves
// the cleanup its full budget. A timed-out test body is abandoned but keeps
// driving the page, so the teardown leaves the app first: a late `Zapisz` must
// not create a record after the sweep.
import { test as base } from '@playwright/test';
import { cleanupBySuffix, uniqueSuffix } from './api';

export { expect } from '@playwright/test';

export const test = base.extend<{ suffix: string }>({
    suffix: [
        async ({ page }, use, testInfo) => {
            const suffix = uniqueSuffix(testInfo);
            await use(suffix);
            await page.goto('about:blank');
            await cleanupBySuffix(page, suffix);
        },
        { timeout: 30_000 },
    ],
});
